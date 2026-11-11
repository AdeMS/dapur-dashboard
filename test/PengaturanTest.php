<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\ConfigProvider;
use AdeMS\Dapur\Dashboard\DashboardUtamaConfigProvider;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\TokenCsrf;
use AdeMS\Dapur\MultiWebsite\Website;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/BantuanUji.php';

final class PengaturanTest extends TestCase
{
    public function testAlamatDasarAkar(): void
    {
        $atur = new Pengaturan(['alamat_dasar' => '/']);

        self::assertSame('/', $atur->rute());
        self::assertSame('/masuk', $atur->rute('/masuk'));
    }

    public function testAlamatDasarBawaan(): void
    {
        $atur = new Pengaturan([]);

        self::assertSame('/dashboard', $atur->rute());
        self::assertSame('/dashboard/masuk', $atur->rute('/masuk'));
    }

    public function testGarisMiringAkhirDibuang(): void
    {
        self::assertSame('/admin/masuk', (new Pengaturan(['alamat_dasar' => '/admin/']))->rute('/masuk'));
    }

    /** @return array<string, array{mixed}> */
    public static function alamatTidakSah(): array
    {
        return [
            'kosong'       => [''],
            'tanpa miring' => ['admin'],
            'keluar folder' => ['/../admin'],
            'tanda kurung' => ['/{id}'],
            'bukan teks'   => [5],
        ];
    }

    #[DataProvider('alamatTidakSah')]
    public function testAlamatTidakSahDitolak(mixed $alamat): void
    {
        $this->expectException(RuntimeException::class);

        new Pengaturan(['alamat_dasar' => $alamat]);
    }

    public function testLapisDasarTanpaHalamanUtama(): void
    {
        $config = (new ConfigProvider())();

        self::assertFalse($config['dashboard']['utama']);
        self::assertSame('/dashboard', $config['dashboard']['alamat_dasar']);
        self::assertSame(['dashboard.ringkasan'], array_column($config['dashboard']['menu'], 'rute'));
    }

    #[RunInSeparateProcess]
    public function testDashboardUtamaDiInti(): void
    {
        $config = (new DashboardUtamaConfigProvider())();

        self::assertTrue($config['dashboard']['utama']);
        self::assertSame('/', $config['dashboard']['alamat_dasar']);
        self::assertContains('dashboard.website', array_column($config['dashboard']['menu'], 'rute'));
    }

    #[RunInSeparateProcess]
    public function testDashboardUtamaKosongDiWebsite(): void
    {
        Website::aktifkan(Website::dariFolderDomain('blog.contoh.com', '/tmp/blog'));

        self::assertSame([], (new DashboardUtamaConfigProvider())());
    }

    public function testTokenCsrf(): void
    {
        $sesi  = new SesiLarik();
        $csrf  = new TokenCsrf($sesi);
        $token = $csrf->token();

        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->sah($token));
        self::assertFalse($csrf->sah('salah'));
        self::assertFalse($csrf->sah(null));
        self::assertFalse($csrf->sah(['larik']));

        $csrf->baru();
        self::assertFalse($csrf->sah($token));
        self::assertNotSame($token, $csrf->token());
    }

    public function testTokenCsrfTanpaTokenTidakSah(): void
    {
        self::assertFalse((new TokenCsrf(new SesiLarik()))->sah(''));
    }
}
