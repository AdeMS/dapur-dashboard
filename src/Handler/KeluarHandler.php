<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\Sesi;
use AdeMS\Dapur\Dashboard\TokenCsrf;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Mengakhiri login; hanya menerima POST dengan token CSRF yang sah. */
final class KeluarHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Pengaturan $pengaturan,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sesi = $request->getAttribute(Sesi::class);
        assert($sesi instanceof Sesi);

        $isi = (array) $request->getParsedBody();
        if (! (new TokenCsrf($sesi))->sah($isi[TokenCsrf::FIELD] ?? null)) {
            return new RedirectResponse($this->pengaturan->rute());
        }

        $sesi->hancurkan();

        return new RedirectResponse($this->pengaturan->rute('/masuk'));
    }
}
