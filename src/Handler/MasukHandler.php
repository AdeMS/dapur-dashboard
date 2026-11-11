<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Dashboard\Autentikasi;
use AdeMS\Dapur\Dashboard\HasilMasuk;
use AdeMS\Dapur\Dashboard\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\Sesi;
use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\Dashboard\TokenCsrf;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Halaman dan proses login. */
final class MasukHandler implements RequestHandlerInterface
{
    private const TEMPLATE = 'dashboard::masuk';

    public function __construct(
        private readonly Autentikasi $autentikasi,
        private readonly Tampilan $tampilan,
        private readonly Pengaturan $pengaturan,
        private readonly string $lingkup,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sesi = $request->getAttribute(Sesi::class);
        assert($sesi instanceof Sesi);

        if ($request->getMethod() !== 'POST') {
            return $sesi->ambil(WajibMasukMiddleware::SESI_USER) !== null
                ? new RedirectResponse($this->pengaturan->rute())
                : $this->tampilan->halaman(self::TEMPLATE, $request, ['pesan' => null, 'email' => '']);
        }

        $isi   = (array) $request->getParsedBody();
        $email = is_string($isi['email'] ?? null) ? $isi['email'] : '';
        $csrf  = new TokenCsrf($sesi);

        if (! $csrf->sah($isi[TokenCsrf::FIELD] ?? null)) {
            return $this->gagal($request, $email, 'Halaman sudah kedaluwarsa. Silakan coba lagi.', 400);
        }

        $hasil = $this->autentikasi->masuk(
            $email,
            is_string($isi['kata_sandi'] ?? null) ? $isi['kata_sandi'] : '',
            (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''),
            $this->lingkup,
        );

        if ($hasil->status === HasilMasuk::DIBATASI) {
            return $this->gagal($request, $email, 'Terlalu banyak percobaan. Silakan coba lagi nanti.', 429);
        }

        if ($hasil->status !== HasilMasuk::BERHASIL || $hasil->user === null) {
            return $this->gagal($request, $email, 'Email atau kata sandi salah.', 401);
        }

        $sesi->gantiId();
        $csrf->baru();
        $sesi->simpan(WajibMasukMiddleware::SESI_USER, $hasil->user->id);
        $sesi->simpan(WajibMasukMiddleware::SESI_TERAKHIR, time());

        return new RedirectResponse($this->pengaturan->rute());
    }

    private function gagal(ServerRequestInterface $request, string $email, string $pesan, int $status): ResponseInterface
    {
        return $this->tampilan->halaman(self::TEMPLATE, $request, ['pesan' => $pesan, 'email' => $email], $status);
    }
}
