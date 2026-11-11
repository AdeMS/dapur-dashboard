<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\Autentikasi;
use AdeMS\Dapur\Dashboard\DaftarUser;
use AdeMS\Dapur\Dashboard\HasilMasuk;
use AdeMS\Dapur\Dashboard\PembatasPercobaan;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/BantuanUji.php';

final class AutentikasiTest extends TestCase
{
    private const SANDI = 'kata-sandi-yang-panjang';
    private const IP    = '192.0.2.1';

    private Adapter $db;
    private DaftarUser $daftar;
    private Autentikasi $autentikasi;
    private int $id;

    protected function setUp(): void
    {
        $this->db          = DatabaseUji::baru();
        $this->daftar      = new DaftarUser($this->db);
        $this->autentikasi = new Autentikasi($this->daftar, new PembatasPercobaan($this->db));
        $this->id          = $this->daftar->buat('Admin@Contoh.com', 'Admin', self::SANDI);
        $this->daftar->tambahAkses($this->id, 'dashboard');
    }

    private function masuk(string $sandi = self::SANDI, string $email = 'admin@contoh.com', string $lingkup = 'dashboard', string $ip = self::IP): HasilMasuk
    {
        return $this->autentikasi->masuk($email, $sandi, $ip, $lingkup);
    }

    public function testBerhasilDanEmailTidakPekaHurufBesar(): void
    {
        $hasil = $this->masuk(email: ' ADMIN@contoh.com ');

        self::assertSame(HasilMasuk::BERHASIL, $hasil->status);
        self::assertSame($this->id, $hasil->user?->id);
    }

    public function testKataSandiSalahGagal(): void
    {
        self::assertSame(HasilMasuk::GAGAL, $this->masuk('salah')->status);
    }

    public function testEmailTidakAdaGagal(): void
    {
        self::assertSame(HasilMasuk::GAGAL, $this->masuk(email: 'lain@contoh.com')->status);
    }

    public function testIsianKosongGagal(): void
    {
        self::assertSame(HasilMasuk::GAGAL, $this->masuk('', '')->status);
    }

    public function testUserNonaktifGagal(): void
    {
        $this->db->query('UPDATE adems_user SET aktif = 0 WHERE id = ?', [$this->id]);

        self::assertSame(HasilMasuk::GAGAL, $this->masuk()->status);
    }

    public function testTanpaAksesKeLingkupGagal(): void
    {
        self::assertSame(HasilMasuk::GAGAL, $this->masuk(lingkup: 'blog.contoh.com')->status);
    }

    public function testTerkunciSetelahBatasWalaupunSandiBenar(): void
    {
        for ($i = 0; $i < PembatasPercobaan::BATAS_AKUN_IP; $i++) {
            self::assertSame(HasilMasuk::GAGAL, $this->masuk('salah')->status);
        }

        self::assertSame(HasilMasuk::DIBATASI, $this->masuk()->status);
    }

    public function testIpLainTidakIkutTerkunci(): void
    {
        for ($i = 0; $i < PembatasPercobaan::BATAS_AKUN_IP; $i++) {
            $this->masuk('salah');
        }

        self::assertSame(HasilMasuk::BERHASIL, $this->masuk(ip: '192.0.2.2')->status);
    }

    public function testBerhasilMembersihkanCatatanGagal(): void
    {
        for ($i = 0; $i < PembatasPercobaan::BATAS_AKUN_IP - 1; $i++) {
            $this->masuk('salah');
        }
        $this->masuk();

        for ($i = 0; $i < PembatasPercobaan::BATAS_AKUN_IP - 1; $i++) {
            self::assertSame(HasilMasuk::GAGAL, $this->masuk('salah')->status);
        }
        self::assertSame(HasilMasuk::BERHASIL, $this->masuk()->status);
    }

    public function testCatatanLamaTidakDihitung(): void
    {
        for ($i = 0; $i < PembatasPercobaan::BATAS_AKUN_IP; $i++) {
            $this->masuk('salah');
        }
        $this->db->query('UPDATE adems_percobaan_masuk SET waktu = ?', [gmdate('Y-m-d H:i:s', time() - PembatasPercobaan::JENDELA_DETIK - 1)]);

        self::assertSame(HasilMasuk::BERHASIL, $this->masuk()->status);
    }

    public function testEmailTidakDisimpanApaAdanya(): void
    {
        $this->masuk('salah', 'rahasia@contoh.com');

        $baris = $this->db->query('SELECT kunci FROM adems_percobaan_masuk', [])->toArray();
        self::assertSame(hash('sha256', 'rahasia@contoh.com'), $baris[0]['kunci']);
    }

    public function testKataSandiDisimpanSebagaiHash(): void
    {
        $user = $this->daftar->cariId($this->id);

        self::assertNotSame(self::SANDI, $user?->kataSandi);
        self::assertTrue(password_verify(self::SANDI, (string) $user?->kataSandi));
        self::assertFalse(DaftarUser::perluHashBaru((string) $user?->kataSandi));
    }
}
