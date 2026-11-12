<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\User\Sesi;
use AdeMS\Dapur\User\TokenCsrf;
use AdeMS\Dapur\User\User;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Router\RouteResult;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Merender halaman dashboard dengan data yang selalu dibutuhkan template. */
final class Tampilan
{
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

        return new HtmlResponse($this->renderer->render($template, $data + [
            'layout'       => $this->pengaturan->layout,
            'menu'         => $this->pengaturan->menu,
            'ruteAktif'    => $request->getAttribute(RouteResult::class)?->getMatchedRouteName() ?: null,
            'user'         => $request->getAttribute(User::class),
            'alamatKeluar' => $this->pengaturan->rute('/keluar'),
            'tokenCsrf'    => (new TokenCsrf($sesi))->token(),
            'fieldCsrf'    => TokenCsrf::FIELD,
        ]), $status);
    }
}
