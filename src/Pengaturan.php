<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use RuntimeException;

/** Isi $config['dashboard'] yang sudah diperiksa. */
final class Pengaturan
{
    public readonly string $alamatDasar;
    public readonly bool $utama;
    public readonly string $layout;
    public readonly int $batasDiam;

    /** @var list<array{label: string, rute: string, ikon: string}> */
    public readonly array $menu;

    /** @param array<string, mixed> $config */
    public function __construct(array $config)
    {
        $alamat = $config['alamat_dasar'] ?? ConfigProvider::ALAMAT_DASAR;
        if (! is_string($alamat) || preg_match('#^(/[a-z0-9_-]+)*/?$#', $alamat) !== 1 || $alamat === '') {
            throw new RuntimeException("Pengaturan 'alamat_dasar' dashboard tidak sah.");
        }

        $this->alamatDasar = rtrim($alamat, '/');
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
}
