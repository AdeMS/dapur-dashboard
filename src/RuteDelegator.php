<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Dashboard\Handler\KeluarHandler;
use AdeMS\Dapur\Dashboard\Handler\MasukHandler;
use AdeMS\Dapur\Dashboard\Handler\RingkasanHandler;
use AdeMS\Dapur\Dashboard\Handler\WebsiteHandler;
use AdeMS\Dapur\Dashboard\Middleware\MulaiSesiMiddleware;
use AdeMS\Dapur\Dashboard\Middleware\WajibMasukMiddleware;
use Mezzio\Application;
use Psr\Container\ContainerInterface;

/** Mendaftarkan route dashboard ke Application. */
final class RuteDelegator
{
    public function __invoke(ContainerInterface $container, string $name, callable $callback): Application
    {
        $app = $callback();
        assert($app instanceof Application);

        $atur   = $container->get(Pengaturan::class);
        $publik = [MulaiSesiMiddleware::class];
        $masuk  = [MulaiSesiMiddleware::class, WajibMasukMiddleware::class];

        $app->route($atur->rute('/masuk'), [...$publik, MasukHandler::class], ['GET', 'POST'], 'dashboard.masuk');
        $app->post($atur->rute('/keluar'), [...$masuk, KeluarHandler::class], 'dashboard.keluar');
        $app->get($atur->rute(), [...$masuk, RingkasanHandler::class], 'dashboard.ringkasan');

        if ($atur->utama) {
            $app->get($atur->rute('/website'), [...$masuk, WebsiteHandler::class], 'dashboard.website');
        }

        return $app;
    }
}
