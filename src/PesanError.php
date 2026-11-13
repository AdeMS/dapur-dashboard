<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\User\PengaturanUser;
use App\Error\LogError;
use Throwable;

/**
 * Pesan error untuk admin dashboard, dibaca dari log error Dapur AdeMS 2.8.1 atau lebih baru.
 *
 * Dashboard utama membaca log inti dan semua website (dengan adems/dapur-multiwebsite 1.2); dashboard website
 * hanya membaca log website itu.
 *
 * Tanda "sudah dibaca" berlaku per error, per admin, dan per lingkup, seperti kotak masuk email. Yang disimpan di
 * pengaturan user hanya dua hal: batas waktu "semua error sampai waktu ini sudah dibaca", ditambah kode error
 * sesudah batas itu yang sudah dibaca satu per satu. Setiap kali error ditandai, batas waktu dimajukan melewati
 * error yang sudah dibaca berurutan, sehingga daftar kodenya tetap pendek.
 */
final class PesanError
{
    /** Awalan kunci pengaturan user; diikuti lingkup, misalnya 'dashboard.error_dibaca.dashboard'. */
    public const KUNCI_DIBACA = 'dashboard.error_dibaca';

    /** Angka notifikasi paling besar; lebih dari ini ditampilkan sebagai "99+". */
    public const JUMLAH_MAKS = 99;

    /** Batas panjang nilai pengaturan user (VARCHAR 255): sisa untuk daftar kode setelah batas waktu. */
    private const PANJANG_NILAI = 255;

    /**
     * @param list<string>        $file       lokasi file log yang dibaca
     * @param PengaturanUser|null $pengaturan null bila tabel pengaturan user tidak tersedia
     */
    public function __construct(
        private readonly array $file,
        private readonly ?PengaturanUser $pengaturan,
        private readonly string $lingkup,
    ) {
    }

    /** Apakah log error bisa dibaca (Dapur 2.8.1 atau lebih baru). */
    public function tersedia(): bool
    {
        return $this->file !== [] && class_exists(LogError::class) && method_exists(LogError::class, 'terbaru');
    }

    /**
     * Apakah tanda "sudah dibaca" bisa disimpan. False bila tabel pengaturan user belum dibuat (perintah pasang dari
     * adems/dapur-user 2.0 belum dijalankan); angka notifikasi lalu tidak bisa berkurang.
     */
    public function bisaMenandai(): bool
    {
        return $this->pengaturan !== null && $this->pengaturan->tersedia();
    }

    /**
     * Error terbaru dari semua file, terbaru lebih dulu, masing-masing dengan kunci 'baru' (belum dibaca user ini).
     * Bila kode diberikan, hanya error dengan kode itu.
     *
     * @return list<array<string, mixed>>
     */
    public function terbaru(int $userId, int $jumlah, ?string $kode = null): array
    {
        $semua = [];
        foreach ($this->log() as $log) {
            array_push($semua, ...$log->baca($jumlah, $kode));
        }

        usort($semua, self::urutTerbaru(...));
        $status = $this->status($userId);

        return array_map(
            fn (array $e): array => $e + ['baru' => $this->belumDibaca($e, $status)],
            array_slice($semua, 0, max(1, $jumlah)),
        );
    }

    /** Jumlah error yang belum dibaca user ini, paling banyak JUMLAH_MAKS. */
    public function jumlahBelumDibaca(int $userId): int
    {
        $status = $this->status($userId);
        $jumlah = 0;
        foreach ($this->log() as $log) {
            foreach ($log->terbaru() as $e) {
                if ($status['sampai'] !== null && strcmp((string) ($e['waktu'] ?? ''), $status['sampai']) <= 0) {
                    break;
                }

                if ($this->belumDibaca($e, $status) && ++$jumlah >= self::JUMLAH_MAKS) {
                    return self::JUMLAH_MAKS;
                }
            }
        }

        return $jumlah;
    }

    /** Menandai satu error (menurut kodenya) sudah dibaca oleh user ini. */
    public function tandaiDibaca(int $userId, string $kode): void
    {
        $kode   = strtoupper($kode);
        $status = $this->status($userId);
        $error  = $this->terbaru($userId, 1, $kode)[0] ?? null;
        if ($error === null || ! $error['baru']) {
            return;
        }

        $status['kode'][] = $kode;
        $this->simpan($userId, $this->padatkan($status));
    }

    /** Menandai semua error sampai yang terbaru sudah dibaca oleh user ini. */
    public function tandaiSemuaDibaca(int $userId): void
    {
        $terbaru = $this->terbaru($userId, 1)[0] ?? null;
        if ($terbaru !== null) {
            $this->simpan($userId, ['sampai' => (string) ($terbaru['waktu'] ?? ''), 'kode' => []]);
        }
    }

    /**
     * Memajukan batas waktu melewati error tertua yang sudah dibaca berurutan, lalu membuang kode yang tidak lagi
     * diperlukan. Bila daftar kode masih terlalu panjang, kode tertua dibuang (error itu tampil baru lagi).
     *
     * @param array{sampai: ?string, kode: list<string>} $status
     * @return array{sampai: ?string, kode: list<string>}
     */
    private function padatkan(array $status): array
    {
        // Error sesudah batas waktu, dari yang tertua.
        $sesudah = [];
        foreach ($this->log() as $log) {
            foreach ($log->terbaru() as $e) {
                if ($status['sampai'] !== null && strcmp((string) ($e['waktu'] ?? ''), $status['sampai']) <= 0) {
                    break;
                }

                $sesudah[] = $e;
            }
        }

        usort($sesudah, static fn (array $a, array $b): int => self::urutTerbaru($b, $a));
        $dibaca = array_flip($status['kode']);
        foreach ($sesudah as $i => $e) {
            $kode  = (string) ($e['kode'] ?? '');
            $waktu = (string) ($e['waktu'] ?? '');
            if (! isset($dibaca[$kode])) {
                break;
            }

            // Batas waktu hanya dimajukan bila tidak ada error lain yang belum dibaca pada detik yang sama.
            foreach (array_slice($sesudah, $i + 1) as $lain) {
                if ((string) ($lain['waktu'] ?? '') === $waktu && ! isset($dibaca[(string) ($lain['kode'] ?? '')])) {
                    break 2;
                }
            }

            $status['sampai'] = $waktu;
            unset($dibaca[$kode]);
        }

        // Kode yang tersisa hanya yang masih ada sesudah batas waktu baru.
        $masihAda = [];
        foreach ($sesudah as $e) {
            if ($status['sampai'] === null || strcmp((string) ($e['waktu'] ?? ''), $status['sampai']) > 0) {
                $masihAda[(string) ($e['kode'] ?? '')] = true;
            }
        }

        $status['kode'] = array_values(array_filter(array_keys($dibaca), static fn (string $k): bool => isset($masihAda[$k])));

        return $status;
    }

    /**
     * @param array<string, mixed>                       $e
     * @param array{sampai: ?string, kode: list<string>} $status
     */
    private function belumDibaca(array $e, array $status): bool
    {
        if ($status['sampai'] !== null && strcmp((string) ($e['waktu'] ?? ''), $status['sampai']) <= 0) {
            return false;
        }

        return ! in_array((string) ($e['kode'] ?? ''), $status['kode'], true);
    }

    /**
     * Isi pengaturan "sudah dibaca", disimpan sebagai "<waktu>|<kode>,<kode>,...".
     *
     * @return array{sampai: ?string, kode: list<string>}
     */
    private function status(int $userId): array
    {
        try {
            $nilai = (string) $this->pengaturan?->ambil($userId, $this->kunci());
        } catch (Throwable) {
            $nilai = '';
        }

        [$sampai, $kode] = array_pad(explode('|', $nilai, 2), 2, '');

        return [
            'sampai' => $sampai === '' ? null : $sampai,
            'kode'   => array_values(array_filter(explode(',', $kode), static fn (string $k): bool => preg_match('/^[A-F0-9]{8}$/', $k) === 1)),
        ];
    }

    /** @param array{sampai: ?string, kode: list<string>} $status */
    private function simpan(int $userId, array $status): void
    {
        if ($this->pengaturan === null) {
            return;
        }

        $kode = $status['kode'];
        do {
            $nilai = ($status['sampai'] ?? '') . '|' . implode(',', $kode);
        } while (strlen($nilai) > self::PANJANG_NILAI && array_shift($kode) !== null);

        try {
            $this->pengaturan->simpan($userId, $this->kunci(), $nilai);
        } catch (Throwable $e) {
            // Tabel pengaturan belum dibuat (perintah pasang belum dijalankan): angka notifikasi tidak berkurang,
            // dan halaman Pesan Error menampilkan pesannya. Kesalahan lain diteruskan agar tercatat di log error.
            if ($this->bisaMenandai()) {
                throw $e;
            }
        }
    }

    private function kunci(): string
    {
        return self::KUNCI_DIBACA . '.' . $this->lingkup;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private static function urutTerbaru(array $a, array $b): int
    {
        return strcmp((string) ($b['waktu'] ?? ''), (string) ($a['waktu'] ?? ''));
    }

    /** @return list<LogError> */
    private function log(): array
    {
        if (! $this->tersedia()) {
            return [];
        }

        return array_map(static fn (string $file): LogError => new LogError($file), $this->file);
    }
}
