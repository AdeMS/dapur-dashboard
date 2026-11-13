<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\ConfigProvider;
use AdeMS\Dapur\Dashboard\DashboardUtamaConfigProvider;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\User\DaftarUser;
use AdeMS\Dapur\User\Middleware\MulaiSesiMiddleware;
use AdeMS\Dapur\MultiWebsite\Website;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        self::assertSame(['dashboard.ringkasan', 'dashboard.user'], array_column($config['dashboard']['menu'], 'rute'));
    }

    public function testPintuMengikutiPengaturanDashboard(): void
    {
        $pintu = (new Pengaturan(['alamat_dasar' => '/admin', 'batas_diam' => 600, 'layout' => 'layout::lain']))->pintu('blog.contoh.com');

        self::assertSame('blog.contoh.com', $pintu->lingkup);
        self::assertSame('/admin/masuk', $pintu->alamatMasuk());
        self::assertSame('/admin/keluar', $pintu->alamatKeluar());
        self::assertSame(600, $pintu->batasDiam);
        self::assertSame('layout::lain', $pintu->layout);
        self::assertTrue($pintu->tujuanSah('/admin/website'));
        self::assertFalse($pintu->tujuanSah('/halaman-publik'));
    }

    public function testPintuDashboardUtamaDiAkar(): void
    {
        $pintu = (new Pengaturan(['alamat_dasar' => '/']))->pintu('dashboard');

        self::assertSame('/masuk', $pintu->alamatMasuk());
        self::assertSame('/profil', $pintu->alamatProfil());
        self::assertTrue($pintu->tujuanSah('/website?urut=nama'));
    }

    public function testLayananUserIkutDimuat(): void
    {
        $config = (new ConfigProvider())();

        self::assertArrayHasKey(DaftarUser::class, $config['dependencies']['factories']);
        self::assertArrayHasKey(MulaiSesiMiddleware::class, $config['dependencies']['factories']);
        self::assertArrayHasKey(ConfigProvider::MASUK, $config['dependencies']['factories']);
        self::assertArrayHasKey(ConfigProvider::PROFIL, $config['dependencies']['factories']);
        self::assertArrayHasKey(ConfigProvider::KELOLA_USER, $config['dependencies']['factories']);
        self::assertArrayHasKey('user', $config['templates']['paths']);
        self::assertArrayHasKey('dashboard', $config['templates']['paths']);
        // Tanpa nilai bawaan, agar pengaturan 'user' inti tidak tertimpa saat modul dipasang di website.
        self::assertArrayNotHasKey('user', $config);
    }

    #[RunInSeparateProcess]
    public function testDashboardUtamaDiInti(): void
    {
        $config = (new DashboardUtamaConfigProvider())();

        self::assertTrue($config['dashboard']['utama']);
        self::assertSame('/', $config['dashboard']['alamat_dasar']);
        self::assertSame(
            ['dashboard.ringkasan', 'dashboard.website', 'dashboard.user'],
            array_column($config['dashboard']['menu'], 'rute'),
        );
    }

    #[RunInSeparateProcess]
    public function testDashboardUtamaKosongDiWebsite(): void
    {
        Website::aktifkan(Website::dariFolderDomain('blog.contoh.com', '/tmp/blog'));

        self::assertSame([], (new DashboardUtamaConfigProvider())());
    }

    public function testMenuUserHanyaUntukAdmin(): void
    {
        $atur = new Pengaturan((new ConfigProvider())()['dashboard']);
        $menu = array_column($atur->menu, 'peran', 'rute');

        self::assertNull($menu['dashboard.ringkasan']);
        self::assertSame('admin', $menu['dashboard.user']);
    }
}
