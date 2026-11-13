<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\User\PenyajiHalaman;
use AdeMS\Dapur\User\Sesi;
use AdeMS\Dapur\User\TokenCsrf;
use AdeMS\Dapur\User\User;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Router\RouteResult;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Merender halaman dashboard di dalam kerangka dashboard (menu samping di kiri dan menu user di navbar),
 * dengan data yang selalu dibutuhkan template. Dipakai juga oleh halaman Profil dan kelola user dari paket
 * user, agar halaman itu tampil dengan menu dashboard.
 */
final class Tampilan implements PenyajiHalaman
{
    /** Template kerangka yang membungkus setiap halaman dashboard. */
    public const KERANGKA = 'dashboard::kerangka';

    public function __construct(
        private readonly TemplateRendererInterface $renderer,
        private readonly Pengaturan $pengaturan,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function halaman(
        string $template,
        ServerRequestInterface $request,
        array $data = [],
        int $status = 200,
    ): ResponseInterface {
        $sesi = $request->getAttribute(Sesi::class);
        assert($sesi instanceof Sesi);

        $data += [
            'layout'       => $this->pengaturan->layout,
            'menu'         => $this->menu($request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN)),
            'ruteAktif'    => $request->getAttribute(RouteResult::class)?->getMatchedRouteName() ?: null,
            'user'         => $request->getAttribute(User::class),
            'alamatKeluar' => $this->pengaturan->rute('/keluar'),
            'alamatProfil' => $this->pengaturan->rute('/profil'),
            'peranUser'    => $request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN),
            'tokenCsrf'    => (new TokenCsrf($sesi))->token(),
            'fieldCsrf'    => TokenCsrf::FIELD,
        ];

        // Halaman dirender tanpa layout, lalu dimasukkan ke kerangka dashboard (menu samping + menu user),
        // dan kerangka itulah yang memakai layout website.
        $isi = $this->renderer->render($template, ['layout' => false] + $data);

        return new HtmlResponse($this->renderer->render(self::KERANGKA, ['isi' => $isi] + $data), $status);
    }

    /**
     * Menu yang boleh dilihat user ini: item dengan 'peran' hanya tampil untuk peran tersebut.
     *
     * @return list<array{label: string, rute: string, ikon: string, peran: ?string}>
     */
    private function menu(mixed $peran): array
    {
        return array_values(array_filter(
            $this->pengaturan->menu,
            static fn (array $item): bool => $item['peran'] === null || $item['peran'] === $peran,
        ));
    }
}
