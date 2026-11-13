<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\User\Pintu;

/** Isi $config['dashboard'] yang sudah diperiksa. */
final class Pengaturan
{
    public readonly string $alamatDasar;
    public readonly bool $utama;
    public readonly string $layout;
    public readonly int $batasDiam;

    /** @var list<array{label: string, rute: string, ikon: string, peran: ?string}> */
    public readonly array $menu;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $this->alamatDasar = Pintu::normalkanAlamat($config['alamat_dasar'] ?? ConfigProvider::ALAMAT_DASAR);
        $this->utama       = ($config['utama'] ?? false) === true;
        $this->layout      = is_string($config['layout'] ?? null) ? $config['layout'] : 'layout::default';
        $this->batasDiam   = max(60, (int) ($config['batas_diam'] ?? 3600));

        $menu = [];
        foreach (is_array($config['menu'] ?? null) ? $config['menu'] : [] as $item) {
            if (is_array($item) && is_string($item['label'] ?? null) && is_string($item['rute'] ?? null)) {
                $menu[] = [
                    'label' => $item['label'],
                    'rute'  => $item['rute'],
                    'ikon'  => is_string($item['ikon'] ?? null) ? $item['ikon'] : 'circle',
                    'peran' => is_string($item['peran'] ?? null) ? $item['peran'] : null,
                ];
            }
        }
        $this->menu = $menu;
    }

    /** Alamat lengkap untuk akhiran seperti '/masuk'; akhiran kosong berarti halaman ringkasan. */
    public function rute(string $akhiran = ''): string
    {
        $alamat = $this->alamatDasar . $akhiran;

        return $alamat === '' ? '/' : $alamat;
    }

    /** Pintu login dashboard untuk lingkup akses tertentu. */
    public function pintu(string $lingkup): Pintu
    {
        return new Pintu($lingkup, $this->rute(), $this->batasDiam, $this->layout);
    }
}
