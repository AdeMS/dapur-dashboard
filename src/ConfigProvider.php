<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Asset\ConfigProvider as AsetConfigProvider;
use AdeMS\Dapur\Asset\PenjagaAkses;
use AdeMS\Dapur\Berkas\ConfigProvider as BerkasConfigProvider;
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

    /** Berkas (adems/dapur-berkas): penjelajah, membuka berkas, dan tautan publik. */
    public const BERKAS        = 'dashboard.handler.berkas';
    public const BERKAS_BUKA   = 'dashboard.handler.berkas_buka';
    public const BERKAS_PUBLIK = 'dashboard.handler.berkas_publik';

    /** Halaman tautan email (tanpa login): lupa kata sandi, membuat kata sandi, dan konfirmasi email baru. */
    public const LUPA_SANDI       = 'dashboard.handler.lupa_sandi';
    public const ATUR_SANDI       = 'dashboard.handler.atur_sandi';
    public const VERIFIKASI_EMAIL = 'dashboard.handler.verifikasi_email';

    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $user = (new UserConfigProvider())();
        $aset   = (new AsetConfigProvider())();
        $berkas = (new BerkasConfigProvider())();

        return [
            'dashboard'    => [
                'alamat_dasar' => self::ALAMAT_DASAR,
                'utama'        => false,
                'layout'       => 'layout::default',
                'batas_diam'   => 3600,
                'menu'         => [
                    ['label' => 'Ringkasan', 'rute' => 'dashboard.ringkasan', 'ikon' => 'speedometer2'],
                    ['label' => 'Berkas', 'rute' => 'dashboard.berkas', 'ikon' => 'folder2-open'],
                    // 'peran': menu hanya tampil untuk user dengan peran ini ('admin' = admin super atau admin web).
                    // 'kelompok': judul kelompok di menu samping (tanpa kunci ini: 'Dashboard').
                    ['label' => 'User', 'rute' => 'dashboard.user', 'ikon' => 'people', 'peran' => 'admin', 'kelompok' => 'Pengaturan'],
                ],
            ],
            // Skema tabel user ikut terdaftar, agar `php dapur pasang` memasangnya (adems/dapur-database).
            'dapur'        => ['skema' => [...($user['dapur']['skema'] ?? []), ...$aset['dapur']['skema'], ...$berkas['dapur']['skema']]],
            'templates'    => [
                'paths' => $user['templates']['paths'] + $berkas['templates']['paths'] + [
                    'dashboard' => [dirname(__DIR__) . '/templates'],
                ],
            ],
            'dependencies' => [
                'factories'  => $user['dependencies']['factories'] + $aset['dependencies']['factories'] + $berkas['dependencies']['factories'] + array_fill_keys([
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
                    self::BERKAS,
                    self::BERKAS_BUKA,
                    self::BERKAS_PUBLIK,
                    self::LUPA_SANDI,
                    self::ATUR_SANDI,
                    self::VERIFIKASI_EMAIL,
                    PesanError::class,
                    Handler\PesanErrorHandler::class,
                    Handler\RingkasanHandler::class,
                    Handler\WebsiteHandler::class,
                    Handler\AsetHandler::class,
                    // Aset ber-akses "login" (adems/dapur-asset) diperiksa dengan login dashboard.
                    PenjagaAkses::class,
                ], PabrikLayanan::class),
                // Route /asset/... dan pengirim file aset ikut terdaftar (aman bila modul aset juga dimuat sendiri).
                'delegators' => [
                    Application::class => [...$aset['dependencies']['delegators'][Application::class], RuteDelegator::class],
                ] + $aset['dependencies']['delegators'],
            ],
            'view_helpers' => $aset['view_helpers'],
        ];
    }
}
