<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use Mezzio\Application;

/**
 * Lapis dasar dashboard: login, session, tata letak, dan menu.
 *
 * Untuk website, cantumkan kelas ini pada 'modul' di config/website.php milik website tersebut.
 * Untuk dashboard utama di inti, pakai DashboardUtamaConfigProvider.
 */
final class ConfigProvider
{
    public const ALAMAT_DASAR = '/dashboard';

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        return [
            'dashboard' => [
                'alamat_dasar' => self::ALAMAT_DASAR,
                'utama'        => false,
                'layout'       => 'layout::default',
                'batas_diam'   => 3600,
                'menu'         => [
                    ['label' => 'Ringkasan', 'rute' => 'dashboard.ringkasan', 'ikon' => 'speedometer2'],
                ],
            ],
            'templates'    => [
                'paths' => [
                    'dashboard' => [dirname(__DIR__) . '/templates'],
                ],
            ],
            'dependencies' => [
                'factories'  => array_fill_keys([
                    Pengaturan::class,
                    DaftarUser::class,
                    PembatasPercobaan::class,
                    Autentikasi::class,
                    Tampilan::class,
                    Middleware\MulaiSesiMiddleware::class,
                    Middleware\WajibMasukMiddleware::class,
                    Handler\MasukHandler::class,
                    Handler\KeluarHandler::class,
                    Handler\RingkasanHandler::class,
                    Handler\WebsiteHandler::class,
                ], PabrikLayanan::class),
                'delegators' => [
                    Application::class => [RuteDelegator::class],
                ],
            ],
        ];
    }
}
