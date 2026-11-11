<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Dashboard\Handler\KeluarHandler;
use AdeMS\Dapur\Dashboard\Handler\MasukHandler;
use AdeMS\Dapur\Dashboard\Handler\RingkasanHandler;
use AdeMS\Dapur\Dashboard\Handler\WebsiteHandler;
use AdeMS\Dapur\Dashboard\Middleware\MulaiSesiMiddleware;
use AdeMS\Dapur\Dashboard\Middleware\WajibMasukMiddleware;
use Laminas\Db\Adapter\Adapter;
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
            DaftarUser::class => new DaftarUser($c->get(Adapter::class)),
            PembatasPercobaan::class => new PembatasPercobaan($c->get(Adapter::class)),
            Autentikasi::class => new Autentikasi($c->get(DaftarUser::class), $c->get(PembatasPercobaan::class)),
            Tampilan::class => new Tampilan($c->get(TemplateRendererInterface::class), $c->get(Pengaturan::class)),
            MulaiSesiMiddleware::class => new MulaiSesiMiddleware(Konteks::folderSesi()),
            WajibMasukMiddleware::class => new WajibMasukMiddleware(
                $c->get(DaftarUser::class),
                $c->get(Pengaturan::class),
                Konteks::lingkup(),
            ),
            MasukHandler::class => new MasukHandler(
                $c->get(Autentikasi::class),
                $c->get(Tampilan::class),
                $c->get(Pengaturan::class),
                Konteks::lingkup(),
            ),
            KeluarHandler::class => new KeluarHandler($c->get(Pengaturan::class)),
            RingkasanHandler::class => new RingkasanHandler($c->get(Tampilan::class)),
            WebsiteHandler::class => new WebsiteHandler($c->get(Tampilan::class), Konteks::folderInti()),
            default => throw new RuntimeException("Layanan dashboard tidak dikenal: {$nama}"),
        };
    }
}
