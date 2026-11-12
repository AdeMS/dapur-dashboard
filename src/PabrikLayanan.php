<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Dashboard\Handler\RingkasanHandler;
use AdeMS\Dapur\Dashboard\Handler\WebsiteHandler;
use AdeMS\Dapur\User\Konteks;
use AdeMS\Dapur\User\PabrikLayanan as PabrikUser;
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
            Tampilan::class => new Tampilan($c->get(TemplateRendererInterface::class), $c->get(Pengaturan::class)),
            ConfigProvider::WAJIB_MASUK => PabrikUser::wajibMasuk($c, self::pintu($c)),
            ConfigProvider::MASUK => PabrikUser::masuk($c, self::pintu($c)),
            ConfigProvider::KELUAR => PabrikUser::keluar($c, self::pintu($c)),
            RingkasanHandler::class => new RingkasanHandler($c->get(Tampilan::class)),
            WebsiteHandler::class => new WebsiteHandler($c->get(Tampilan::class), Konteks::folderInti()),
            default => throw new RuntimeException("Layanan dashboard tidak dikenal: {$nama}"),
        };
    }

    /** Pintu login dashboard: lingkup 'dashboard' di inti, atau nama domain di website. */
    private static function pintu(ContainerInterface $c): Pintu
    {
        return $c->get(Pengaturan::class)->pintu(Konteks::lingkup());
    }
}
