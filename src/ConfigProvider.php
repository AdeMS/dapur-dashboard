<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\User\ConfigProvider as UserConfigProvider;
use Mezzio\Application;

/**
 * Lapis dasar dashboard: halaman, tata letak, menu, dan pintu login dari paket adems/dapur-user.
 *
 * Untuk website, cantumkan kelas ini pada 'modul' di config/website.php milik website tersebut.
 * Untuk dashboard utama di inti, pakai DashboardUtamaConfigProvider. Layanan paket user ikut dimuat dari
 * sini, jadi UserConfigProvider tidak perlu dicantumkan sendiri.
 */
final class ConfigProvider
{
    public const ALAMAT_DASAR = '/dashboard';

    /** Nama layanan login untuk pintu dashboard. */
    public const WAJIB_MASUK = 'dashboard.wajib_masuk';
    public const MASUK       = 'dashboard.handler.masuk';
    public const KELUAR      = 'dashboard.handler.keluar';
    public const PROFIL      = 'dashboard.handler.profil';
    public const KELOLA_USER = 'dashboard.handler.user';

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $user = (new UserConfigProvider())();

        return [
            'dashboard'    => [
                'alamat_dasar' => self::ALAMAT_DASAR,
                'utama'        => false,
                'layout'       => 'layout::default',
                'batas_diam'   => 3600,
                'menu'         => [
                    ['label' => 'Ringkasan', 'rute' => 'dashboard.ringkasan', 'ikon' => 'speedometer2'],
                    // 'peran': menu hanya tampil untuk user dengan peran ini ('admin' = admin super atau admin web)
                    ['label' => 'User', 'rute' => 'dashboard.user', 'ikon' => 'people', 'peran' => 'admin'],
                ],
            ],
            'templates'    => [
                'paths' => $user['templates']['paths'] + [
                    'dashboard' => [dirname(__DIR__) . '/templates'],
                ],
            ],
            'dependencies' => [
                'factories'  => $user['dependencies']['factories'] + array_fill_keys([
                    Pengaturan::class,
                    Tampilan::class,
                    self::WAJIB_MASUK,
                    self::MASUK,
                    self::KELUAR,
                    self::PROFIL,
                    self::KELOLA_USER,
                    PesanError::class,
                    Handler\PesanErrorHandler::class,
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
