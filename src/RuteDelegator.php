<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Dashboard\Handler\PesanErrorHandler;
use AdeMS\Dapur\Dashboard\Handler\RingkasanHandler;
use AdeMS\Dapur\Dashboard\Handler\WebsiteHandler;
use AdeMS\Dapur\User\Middleware\MulaiSesiMiddleware;
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
        $masuk  = [MulaiSesiMiddleware::class, ConfigProvider::WAJIB_MASUK];

        $app->route($atur->rute('/masuk'), [...$publik, ConfigProvider::MASUK], ['GET', 'POST'], 'dashboard.masuk');
        $app->route($atur->rute('/lupa-sandi'), [...$publik, ConfigProvider::LUPA_SANDI], ['GET', 'POST'], 'dashboard.lupa_sandi');
        $app->route($atur->rute('/atur-sandi'), [...$publik, ConfigProvider::ATUR_SANDI], ['GET', 'POST'], 'dashboard.atur_sandi');
        $app->route($atur->rute('/verifikasi-email'), [...$publik, ConfigProvider::VERIFIKASI_EMAIL], ['GET', 'POST'], 'dashboard.verifikasi_email');
        $app->post($atur->rute('/keluar'), [...$masuk, ConfigProvider::KELUAR], 'dashboard.keluar');
        $app->get($atur->rute(), [...$masuk, RingkasanHandler::class], 'dashboard.ringkasan');
        $app->route($atur->rute('/profil'), [...$masuk, ConfigProvider::PROFIL], ['GET', 'POST'], 'dashboard.profil');
        $app->route($atur->rute('/pengaturan'), [...$masuk, ConfigProvider::PENGATURAN], ['GET', 'POST'], 'dashboard.pengaturan');
        $app->route($atur->rute('/user'), [...$masuk, ConfigProvider::KELOLA_USER], ['GET', 'POST'], 'dashboard.user');
        $app->route($atur->rute('/error'), [...$masuk, PesanErrorHandler::class], ['GET', 'POST'], 'dashboard.error');

        if ($atur->utama) {
            $app->get($atur->rute('/website'), [...$masuk, WebsiteHandler::class], 'dashboard.website');
        }

        return $app;
    }
}
