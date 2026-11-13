<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\ConfigProvider;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\User\Sesi;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Template\TemplatePath;
use Mezzio\Template\TemplateRendererInterface;
use PHPUnit\Framework\TestCase;

final class TampilanTest extends TestCase
{
    /** @return array<string, mixed> data template yang dirender */
    private function render(?string $peran): array
    {
        $renderer = new class implements TemplateRendererInterface {
            /** @var array<string, mixed> data template terakhir (kerangka) */
            public array $data = [];

            /** @var list<array{0: string, 1: array<string, mixed>}> semua template yang dirender, berurutan */
            public array $render = [];

            public function render(string $name, $params = []): string
            {
                $this->data     = (array) $params;
                $this->render[] = [$name, (array) $params];

                return "<{$name}>";
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
        $tampilan->halaman('dashboard::ringkasan', (new ServerRequest())
            ->withAttribute(Sesi::class, $sesi)
            ->withAttribute(WajibMasukMiddleware::ATRIBUT_PERAN, $peran));

        $this->render = $renderer->render;

        return $renderer->data;
    }

    /** @var list<array{0: string, 1: array<string, mixed>}> */
    private array $render = [];

    public function testAdminMelihatMenuUser(): void
    {
        $data = $this->render('admin');

        self::assertSame(['dashboard.ringkasan', 'dashboard.user'], array_column($data['menu'], 'rute'));
        self::assertSame('admin', $data['peranUser']);
        self::assertSame('/dashboard/profil', $data['alamatProfil']);
    }

    public function testAnggotaTidakMelihatMenuUser(): void
    {
        self::assertSame(['dashboard.ringkasan'], array_column($this->render('anggota')['menu'], 'rute'));
    }

    public function testHalamanDibungkusKerangkaDenganLayoutWebsite(): void
    {
        $data = $this->render('admin');

        self::assertSame(['dashboard::ringkasan', Tampilan::KERANGKA], array_column($this->render, 0));
        self::assertFalse($this->render[0][1]['layout'], 'Halaman dirender tanpa layout.');
        self::assertSame('<dashboard::ringkasan>', $data['isi']);
        self::assertSame('layout::default', $data['layout']);
    }
}
