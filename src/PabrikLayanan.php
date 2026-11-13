<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Dashboard\Handler\PesanErrorHandler;
use AdeMS\Dapur\Dashboard\Handler\RingkasanHandler;
use AdeMS\Dapur\Dashboard\Handler\WebsiteHandler;
use AdeMS\Dapur\User\Konteks;
use AdeMS\Dapur\User\PabrikLayanan as PabrikUser;
use AdeMS\Dapur\User\Pengaturan as PengaturanUser;
use AdeMS\Dapur\User\PengaturanUser as PengaturanMilikUser;
use App\Error\LogError;
use AdeMS\Dapur\User\Pintu;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

/** Satu factory untuk semua layanan paket; berupa kelas agar konfigurasi tetap bisa di-cache. */
final class PabrikLayanan
{
    public function __invoke(ContainerInterface $c, string $nama): object
    {
        return match ($nama) {
            Pengaturan::class => new Pengaturan($c->get('config')['dashboard'] ?? []),
            Tampilan::class => new Tampilan(
                $c->get(TemplateRendererInterface::class),
                $c->get(Pengaturan::class),
                $c->get(PesanError::class),
                $c->get(PengaturanUser::class)->zonaWaktu,
            ),
            PesanError::class => new PesanError(
                self::fileLogError($c),
                $c->has(PengaturanMilikUser::class) ? $c->get(PengaturanMilikUser::class) : null,
                Konteks::lingkup(),
            ),
            PesanErrorHandler::class => new PesanErrorHandler(
                $c->get(Tampilan::class),
                $c->get(PesanError::class),
                $c->get(PengaturanUser::class)->zonaWaktu,
            ),
            ConfigProvider::WAJIB_MASUK => PabrikUser::wajibMasuk($c, self::pintu($c)),
            ConfigProvider::MASUK => PabrikUser::masuk($c, self::pintu($c)),
            ConfigProvider::KELUAR => PabrikUser::keluar($c, self::pintu($c)),
            // Profil dari paket user, dengan menu dashboard (Tampilan) dan template dashboard::profil.
            ConfigProvider::PROFIL => PabrikUser::profil($c, self::pintu($c), $c->get(Tampilan::class), 'dashboard::profil'),
            // Aktif/nonaktif akun berlaku di semua website, jadi hanya diizinkan di dashboard utama.
            ConfigProvider::KELOLA_USER => PabrikUser::kelolaUser(
                $c,
                self::pintu($c),
                $c->get(Tampilan::class),
                'dashboard::user',
                $c->get(Pengaturan::class)->utama,
            ),
            RingkasanHandler::class => new RingkasanHandler($c->get(Tampilan::class)),
            WebsiteHandler::class => new WebsiteHandler($c->get(Tampilan::class), Konteks::folderInti()),
            default => throw new RuntimeException("Layanan dashboard tidak dikenal: {$nama}"),
        };
    }

    /**
     * File log error yang dibaca halaman Pesan Error. Dashboard utama: log inti dan semua website (bila
     * adems/dapur-multiwebsite 1.2 terpasang). Dashboard website: log website itu saja. Kosong bila inti Dapur
     * belum memiliki log error (sebelum 2.8).
     *
     * @return list<string>
     */
    private static function fileLogError(ContainerInterface $c): array
    {
        if (! class_exists(LogError::class) || ! $c->has(LogError::class)) {
            return [];
        }

        $inti = $c->get(LogError::class)->file;
        if (! $c->get(Pengaturan::class)->utama) {
            return [$inti];
        }

        $perintah = 'AdeMS\Dapur\MultiWebsite\Perintah';
        $semua    = class_exists($perintah) && method_exists($perintah, 'fileLogErrorSemua')
            ? (new $perintah(Konteks::folderInti()))->fileLogErrorSemua($inti)
            : null;

        return $semua === null ? [$inti] : array_values(array_filter($semua, 'is_string'));
    }

    /** Pintu login dashboard: lingkup 'dashboard' di inti, atau nama domain di website. */
    private static function pintu(ContainerInterface $c): Pintu
    {
        return $c->get(Pengaturan::class)->pintu(Konteks::lingkup());
    }
}
