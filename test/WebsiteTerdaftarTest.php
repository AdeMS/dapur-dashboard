<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\WebsiteTerdaftar;
use AdeMS\Dapur\User\BatasanDomain;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WebsiteTerdaftarTest extends TestCase
{
    private string $inti;

    protected function setUp(): void
    {
        $this->inti = sys_get_temp_dir() . '/dapur-website-terdaftar-' . bin2hex(random_bytes(4));
        mkdir($this->inti . '/config/website/kantor.co.id/autoload', 0777, true);
        mkdir($this->inti . '/config/website/toko.com', 0777, true);
        mkdir($this->inti . '/domain/blog.com/config', 0777, true);
        file_put_contents($this->inti . '/config/multiwebsite.local.php', '<?php return ' . var_export([
            'dashboard' => ['dapur.contoh.com'],
            'website'   => [
                'toko.com'     => [],
                'kantor.co.id' => [],
                'blog.com'     => ['lokasi' => $this->inti . '/domain/blog.com'],
                'hilang.com'   => [],
            ],
        ], true) . ';');
        file_put_contents(
            $this->inti . '/config/website/kantor.co.id/autoload/user.global.php',
            "<?php return ['user' => ['domain_email' => ['kantor.co.id']]];",
        );
    }

    protected function tearDown(): void
    {
        $hapus = function (string $folder) use (&$hapus): void {
            foreach (scandir($folder) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    is_dir("{$folder}/{$f}") ? $hapus("{$folder}/{$f}") : unlink("{$folder}/{$f}");
                }
            }
            rmdir($folder);
        };
        $hapus($this->inti);
    }

    public function testHanyaWebsiteYangFoldernyaAda(): void
    {
        self::assertTrue(WebsiteTerdaftar::tersedia($this->inti));
        self::assertSame(['blog.com', 'kantor.co.id', 'toko.com'], (new WebsiteTerdaftar($this->inti))->website());
    }

    public function testBatasanDomainDariPengaturanWebsite(): void
    {
        $daftar = new WebsiteTerdaftar($this->inti, new BatasanDomain(['inti.com']));

        self::assertSame(['kantor.co.id'], $daftar->batasanDomain('kantor.co.id')->domain);
        self::assertSame(['inti.com'], $daftar->batasanDomain('toko.com')->domain, 'Tanpa pengaturan website, berlaku pengaturan inti.');
        self::assertSame(['inti.com'], $daftar->batasanDomain('blog.com')->domain);

        $this->expectException(RuntimeException::class);
        $daftar->batasanDomain('hilang.com');
    }

    public function testTanpaDaftarWebsite(): void
    {
        unlink($this->inti . '/config/multiwebsite.local.php');

        self::assertFalse(WebsiteTerdaftar::tersedia($this->inti));
        self::assertSame([], (new WebsiteTerdaftar($this->inti))->website());
    }
}
