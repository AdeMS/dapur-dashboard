<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Dashboard\PesanError;
use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\User\DaftarUser;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\User\Sesi;
use AdeMS\Dapur\User\TokenCsrf;
use AdeMS\Dapur\User\User;
use DateTimeImmutable;
use DateTimeZone;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Halaman Pesan Error untuk admin super dan admin web: error terbaru dari log error, lengkap dengan jejak
 * pemanggilannya.
 *
 * Tanda "sudah dibaca" per error: membuka satu error lewat kodenya (misalnya dari menu notifikasi) atau membuka
 * detailnya di halaman ini (POST aksi=baca dari JavaScript) menandai error itu saja. Tombol "Tandai semua sudah
 * dibaca" (POST aksi=semua) menandai semuanya.
 */
final class PesanErrorHandler implements RequestHandlerInterface
{
    /** Jumlah error per tampilan; tombol "Tampilkan lebih banyak" menambah sebanyak ini. */
    public const JUMLAH = 50;

    /** Paling banyak error yang bisa ditampilkan sekaligus. */
    public const JUMLAH_MAKS = 1000;

    public function __construct(
        private readonly Tampilan $tampilan,
        private readonly PesanError $pesanError,
        private readonly DateTimeZone $zonaWaktu,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user  = $request->getAttribute(User::class);
        $peran = $request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN);
        assert($user instanceof User);

        if (! DaftarUser::peranAdmin(is_string($peran) ? $peran : null)) {
            return $request->getMethod() === 'POST'
                ? new EmptyResponse(403)
                : $this->tampilan->halaman('dashboard::error', $request, ['ditolak' => true] + $this->kosong(), 403);
        }

        if (! $this->pesanError->tersedia()) {
            return $this->tampilan->halaman('dashboard::error', $request, ['tidakTersedia' => true] + $this->kosong());
        }

        if ($request->getMethod() === 'POST') {
            return $this->tandai($request, $user);
        }

        $kode = self::kode($request->getQueryParams()['kode'] ?? null);
        if ($kode !== null) {
            $this->pesanError->tandaiDibaca($user->id, $kode);
        }

        $jumlah = self::jumlah($request->getQueryParams()['jumlah'] ?? null);
        $error  = $this->pesanError->terbaru($user->id, $kode === null ? $jumlah + 1 : self::JUMLAH, $kode);

        // Satu error lebih dari yang diminta hanya untuk mengetahui apakah masih ada yang lebih lama.
        $adaLagi = $kode === null && count($error) > $jumlah;
        $error   = array_slice($error, 0, $jumlah);

        return $this->tampilan->halaman('dashboard::error', $request, [
            'error'       => array_map($this->siapkan(...), $error),
            'kode'        => $kode,
            'adaBaru'     => in_array(true, array_column($error, 'baru'), true),
            'lebihBanyak' => $adaLagi && $jumlah < self::JUMLAH_MAKS ? min($jumlah + self::JUMLAH, self::JUMLAH_MAKS) : null,
            'belumSiap'   => $this->pesanError->bisaMenandai()
                ? null
                : 'Tabel pengaturan user belum dibuat, jadi tanda "sudah dibaca" belum bisa disimpan. Jalankan "vendor/bin/dapur-user pasang" dari folder inti Dapur.',
        ] + $this->kosong());
    }

    /** POST aksi=baca&kode=... (dari JavaScript, jawaban 204) atau aksi=semua (dari tombol, diarahkan kembali). */
    private function tandai(ServerRequestInterface $request, User $user): ResponseInterface
    {
        $sesi = $request->getAttribute(Sesi::class);
        $isi  = (array) $request->getParsedBody();
        if (! $sesi instanceof Sesi || ! (new TokenCsrf($sesi))->sah($isi[TokenCsrf::FIELD] ?? null)) {
            return new EmptyResponse(400);
        }

        if (($isi['aksi'] ?? null) === 'semua') {
            $this->pesanError->tandaiSemuaDibaca($user->id);

            return new RedirectResponse((string) $request->getUri()->withQuery(''));
        }

        $kode = self::kode($isi['kode'] ?? null);
        if (($isi['aksi'] ?? null) !== 'baca' || $kode === null) {
            return new EmptyResponse(400);
        }

        $this->pesanError->tandaiDibaca($user->id, $kode);

        return new EmptyResponse(204);
    }

    /** Jumlah error yang ditampilkan: kelipatan JUMLAH, paling banyak JUMLAH_MAKS. */
    private static function jumlah(mixed $jumlah): int
    {
        $jumlah = is_string($jumlah) && ctype_digit($jumlah) ? (int) $jumlah : self::JUMLAH;

        return max(self::JUMLAH, min(self::JUMLAH_MAKS, intdiv($jumlah, self::JUMLAH) * self::JUMLAH));
    }

    private static function kode(mixed $kode): ?string
    {
        $kode = is_string($kode) ? trim($kode) : '';

        return preg_match('/^[A-Fa-f0-9]{8}$/', $kode) === 1 ? strtoupper($kode) : null;
    }

    /**
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private function siapkan(array $e): array
    {
        return $e + ['waktuSetempat' => self::waktu((string) ($e['waktu'] ?? ''), $this->zonaWaktu)];
    }

    /** Waktu log (UTC) menurut zona waktu tampilan. */
    public static function waktu(string $utc, DateTimeZone $zona): string
    {
        try {
            return (new DateTimeImmutable($utc))->setTimezone($zona)->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return $utc;
        }
    }

    /** @return array<string, mixed> */
    private function kosong(): array
    {
        return [
            'ditolak'       => false,
            'tidakTersedia' => false,
            'error'         => [],
            'kode'          => null,
            'adaBaru'       => false,
            'lebihBanyak'   => null,
            'belumSiap'     => null,
            'zonaWaktu'     => $this->zonaWaktu->getName(),
            'jumlahMaks'    => self::JUMLAH,
        ];
    }
}
