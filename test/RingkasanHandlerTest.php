<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\ConfigProvider;
use AdeMS\Dapur\Dashboard\Handler\RingkasanHandler;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\Database\KonteksSkema;
use AdeMS\Dapur\Database\Pemasang;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\User\Sesi;
use AdeMS\Dapur\User\UserSkema;
use Closure;
use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Template\TemplatePath;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RingkasanHandlerTest extends TestCase
{
    /** @var array<string, mixed> data halaman ringkasan yang dirender */
    private array $data = [];

    private function buka(?string $peran, ?Closure $pemasang): void
    {
        $renderer = new class implements TemplateRendererInterface {
            /** @var array<string, mixed> */
            public array $halaman = [];

            public function render(string $name, $params = []): string
            {
                if ($name === 'dashboard::ringkasan') {
                    $this->halaman = (array) $params;
                }

                return '';
            }

            public function addPath(string $path, ?string $namespace = null): void
            {
            }

            /** @return TemplatePath[] */
            public function getPaths(): array
            {
                return [];
            }

            public function addDefaultParam(string $templateName, string $param, mixed $value): void
            {
            }
        };

        $sesi = new class implements Sesi {
            /** @var array<string, mixed> */
            private array $isi = [];

            public function ambil(string $kunci): mixed
            {
                return $this->isi[$kunci] ?? null;
            }

            public function simpan(string $kunci, mixed $nilai): void
            {
                $this->isi[$kunci] = $nilai;
            }

            public function hapus(string $kunci): void
            {
                unset($this->isi[$kunci]);
            }

            public function gantiId(): void
            {
            }

            public function hancurkan(): void
            {
            }
        };

        $tampilan = new Tampilan($renderer, new Pengaturan((new ConfigProvider())()['dashboard']));
        (new RingkasanHandler($tampilan, $pemasang, 'php dapur pasang'))->handle((new ServerRequest())
            ->withAttribute(Sesi::class, $sesi)
            ->withAttribute(WajibMasukMiddleware::ATRIBUT_PERAN, $peran));

        $this->data = $renderer->halaman;
    }

    private function pemasang(Adapter $db): Closure
    {
        return static fn (): Pemasang => new Pemasang(new KonteksSkema($db), [new UserSkema()]);
    }

    public function testSkemaUserIkutTerdaftarLewatDashboard(): void
    {
        self::assertSame([UserSkema::class], (new ConfigProvider())()['dapur']['skema']);
    }

    public function testAdminSuperDiingatkanBilaDatabasePerluDiperbarui(): void
    {
        $db = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);

        $this->buka('admin_super', $this->pemasang($db));
        self::assertSame(['perlu' => true, 'perintah' => 'php dapur pasang', 'galat' => null], $this->data['database']);

        // Sesudah dipasang, peringatan hilang.
        $p = ($this->pemasang($db))();
        $p->jalankan($p->rencana());
        $this->buka('admin_super', $this->pemasang($db));
        self::assertFalse($this->data['database']['perlu']);
    }

    public function testSelainAdminSuperTidakMemeriksaDatabase(): void
    {
        $dipanggil = false;
        $pemasang  = static function () use (&$dipanggil): Pemasang {
            $dipanggil = true;

            throw new RuntimeException('tidak boleh dipanggil');
        };

        foreach (['admin_web', 'anggota', null] as $peran) {
            $this->buka($peran, $pemasang);
            self::assertNull($this->data['database'], (string) $peran);
        }

        self::assertFalse($dipanggil);
    }

    public function testPemeriksaanGagalDitampilkan(): void
    {
        $this->buka('admin_super', static function (): Pemasang {
            throw new RuntimeException('Modul uang membutuhkan modul user.');
        });

        self::assertTrue($this->data['database']['perlu']);
        self::assertSame('Modul uang membutuhkan modul user.', $this->data['database']['galat']);
    }
}
