<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Middleware;

use AdeMS\Dapur\Dashboard\DaftarUser;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\Sesi;
use AdeMS\Dapur\Dashboard\User;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Mengarahkan pengunjung yang belum login ke halaman masuk. Status user diperiksa ulang setiap request. */
final class WajibMasukMiddleware implements MiddlewareInterface
{
    public const SESI_USER     = 'dashboard.user_id';
    public const SESI_TERAKHIR = 'dashboard.terakhir_aktif';

    public function __construct(
        private readonly DaftarUser $daftar,
        private readonly Pengaturan $pengaturan,
        private readonly string $lingkup,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sesi = $request->getAttribute(Sesi::class);
        assert($sesi instanceof Sesi);

        $id       = $sesi->ambil(self::SESI_USER);
        $terakhir = $sesi->ambil(self::SESI_TERAKHIR);
        $user     = is_int($id) && is_int($terakhir) && time() - $terakhir <= $this->pengaturan->batasDiam
            ? $this->daftar->cariId($id)
            : null;

        if ($user === null || ! $user->aktif || ! $this->daftar->punyaAkses($user->id, $this->lingkup)) {
            if ($id !== null) {
                $sesi->hancurkan();
            }

            return new RedirectResponse($this->pengaturan->rute('/masuk'));
        }

        $sesi->simpan(self::SESI_TERAKHIR, time());

        return $handler->handle($request->withAttribute(User::class, $user));
    }
}
