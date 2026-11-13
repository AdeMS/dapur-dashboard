<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\User\Konteks;

/**
 * Lapis dashboard utama: lapis dasar di alamat '/' ditambah halaman yang menyangkut semua website.
 *
 * Cantumkan kelas ini di config/modul.local.php milik inti. Modul inti ikut dimuat untuk semua
 * website, jadi kelas ini sengaja kosong saat yang dilayani adalah website, sehingga halaman
 * dashboard utama tidak pernah ada di domain website. Cache konfigurasi terpisah per website.
 */
final class DashboardUtamaConfigProvider
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        if (Konteks::websiteAktif() !== null) {
            return [];
        }

        $config = (new ConfigProvider())();

        $config['dashboard']['alamat_dasar'] = '/';
        $config['dashboard']['utama']        = true;

        // Website disisipkan sesudah Ringkasan, sebelum menu lainnya.
        array_splice($config['dashboard']['menu'], 1, 0, [[
            'label' => 'Website',
            'rute'  => 'dashboard.website',
            'ikon'  => 'globe',
        ]]);

        return $config;
    }
}
