<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\DaftarUser;
use AdeMS\Dapur\Dashboard\Perintah;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BantuanUji.php';

final class PerintahTest extends TestCase
{
    private const SANDI = 'kata-sandi-yang-panjang';

    private Adapter $db;
    private DaftarUser $daftar;

    protected function setUp(): void
    {
        $this->db     = DatabaseUji::baru();
        $this->daftar = new DaftarUser($this->db);
    }

    /** @param list<string> $jawaban */
    private function perintah(array $jawaban = [], string $lingkup = 'dashboard'): Perintah
    {
        return new Perintah($this->db, $this->daftar, static function () use (&$jawaban): string {
            return (string) array_shift($jawaban);
        }, $lingkup);
    }

    /** @param list<string> $argumen */
    private function jalankan(Perintah $perintah, array $argumen): int
    {
        ob_start();
        try {
            return $perintah->jalankan($argumen);
        } finally {
            ob_end_clean();
        }
    }

    public function testPasangAmanDiulang(): void
    {
        self::assertSame(0, $this->jalankan($this->perintah(), ['pasang']));
        self::assertSame(0, $this->jalankan($this->perintah(), ['pasang']));
    }

    public function testBuatAdmin(): void
    {
        $kode = $this->jalankan($this->perintah([self::SANDI, self::SANDI]), ['buat-admin', 'admin@contoh.com', '--nama=Ade']);

        $user = $this->daftar->cariEmail('admin@contoh.com');
        self::assertSame(0, $kode);
        self::assertSame('Ade', $user?->nama);
        self::assertTrue(password_verify(self::SANDI, (string) $user?->kataSandi));
        self::assertTrue($this->daftar->punyaAkses((int) $user?->id, 'dashboard'));
    }

    public function testOpsiWebsiteDiabaikanDanLingkupWebsiteDipakai(): void
    {
        $kode = $this->jalankan(
            $this->perintah([self::SANDI, self::SANDI], 'blog.contoh.com'),
            ['--website=blog.contoh.com', 'buat-admin', 'admin@contoh.com'],
        );

        $user = $this->daftar->cariEmail('admin@contoh.com');
        self::assertSame(0, $kode);
        self::assertTrue($this->daftar->punyaAkses((int) $user?->id, 'blog.contoh.com'));
        self::assertFalse($this->daftar->punyaAkses((int) $user?->id, 'dashboard'));
    }

    public function testSyaratKataSandiDitampilkanSaatDiminta(): void
    {
        $pesan    = [];
        $perintah = new Perintah($this->db, $this->daftar, static function (string $teks) use (&$pesan): string {
            $pesan[] = $teks;

            return 'pendek';
        }, 'dashboard');

        $this->jalankan($perintah, ['buat-admin', 'admin@contoh.com']);

        self::assertSame('Kata sandi baru (minimal 12 karakter): ', $pesan[0]);
    }

    public function testGantiSandiUserBiasaCukupDelapanKarakter(): void
    {
        $id = $this->daftar->buat('user@contoh.com', 'User', 'kata-sandi-lama');
        $this->daftar->tambahAkses($id, 'dashboard', 'anggota');

        self::assertSame(0, $this->jalankan($this->perintah(['delapan8', 'delapan8']), ['ganti-sandi', 'user@contoh.com']));
        self::assertTrue(password_verify('delapan8', (string) $this->daftar->cariId($id)?->kataSandi));
        self::assertSame(1, $this->jalankan($this->perintah(['tujuh77']), ['ganti-sandi', 'user@contoh.com']));
    }

    public function testGantiSandiAdminTetapDuaBelasKarakter(): void
    {
        $id = $this->daftar->buat('admin@contoh.com', 'Admin', self::SANDI);
        $this->daftar->tambahAkses($id, 'blog.contoh.com');

        self::assertSame(1, $this->jalankan($this->perintah(['sebelas-111', 'sebelas-111']), ['ganti-sandi', 'admin@contoh.com']));
        self::assertTrue(password_verify(self::SANDI, (string) $this->daftar->cariId($id)?->kataSandi));
    }

    public function testKataSandiPendekDitolak(): void
    {
        self::assertSame(1, $this->jalankan($this->perintah(['pendek']), ['buat-admin', 'admin@contoh.com']));
        self::assertNull($this->daftar->cariEmail('admin@contoh.com'));
    }

    public function testKataSandiTidakSamaDitolak(): void
    {
        self::assertSame(1, $this->jalankan($this->perintah([self::SANDI, 'berbeda-yang-panjang']), ['buat-admin', 'admin@contoh.com']));
        self::assertNull($this->daftar->cariEmail('admin@contoh.com'));
    }

    public function testEmailTidakSahDitolak(): void
    {
        self::assertSame(1, $this->jalankan($this->perintah(), ['buat-admin', 'bukan-email']));
    }

    public function testUserAdaHanyaDitambahAksesTanpaGantiSandi(): void
    {
        $id = $this->daftar->buat('admin@contoh.com', 'Admin', self::SANDI);

        $kode = $this->jalankan($this->perintah([], 'blog.contoh.com'), ['buat-admin', 'admin@contoh.com']);

        self::assertSame(0, $kode);
        self::assertTrue($this->daftar->punyaAkses($id, 'blog.contoh.com'));
        self::assertTrue(password_verify(self::SANDI, (string) $this->daftar->cariId($id)?->kataSandi));
    }

    public function testGantiSandi(): void
    {
        $id   = $this->daftar->buat('admin@contoh.com', 'Admin', self::SANDI);
        $baru = 'kata-sandi-baru-yang-panjang';

        self::assertSame(0, $this->jalankan($this->perintah([$baru, $baru]), ['ganti-sandi', 'admin@contoh.com']));
        self::assertTrue(password_verify($baru, (string) $this->daftar->cariId($id)?->kataSandi));
    }

    public function testPerintahTidakDikenal(): void
    {
        self::assertSame(1, $this->jalankan($this->perintah(), ['tidak-ada']));
    }
}
