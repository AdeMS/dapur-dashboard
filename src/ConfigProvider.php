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
    public const PENGATURAN  = 'dashboard.handler.pengaturan';
    public const KELOLA_USER = 'dashboard.handler.user';
    public const FOTO        = 'dashboard.handler.foto';
    public const LOKASI      = 'dashboard.handler.lokasi';

    /** Halaman tautan email (tanpa login): lupa kata sandi, membuat kata sandi, dan konfirmasi email baru. */
    public const LUPA_SANDI       = 'dashboard.handler.lupa_sandi';
    public const ATUR_SANDI       = 'dashboard.handler.atur_sandi';
    public const VERIFIKASI_EMAIL = 'dashboard.handler.verifikasi_email';

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
                    // 'peran': menu hanya tampil untuk user dengan peran ini ('admin' = admin super atau admin web).
                    // 'kelompok': judul kelompok di menu samping (tanpa kunci ini: 'Dashboard').
                    ['label' => 'User', 'rute' => 'dashboard.user', 'ikon' => 'people', 'peran' => 'admin', 'kelompok' => 'Pengaturan'],
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
                    self::PENGATURAN,
                    self::KELOLA_USER,
                    self::FOTO,
                    self::LOKASI,
                    self::LUPA_SANDI,
                    self::ATUR_SANDI,
                    self::VERIFIKASI_EMAIL,
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
