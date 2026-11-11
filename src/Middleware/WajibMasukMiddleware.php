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
    /** Alamat yang diminta sebelum login; dituju lagi setelah login berhasil. */
    public const SESI_TUJUAN = 'dashboard.tujuan';

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
                // Session tidak dihancurkan agar alamat tujuan di bawah tetap tersimpan.
                $sesi->hapus(self::SESI_USER);
                $sesi->hapus(self::SESI_TERAKHIR);
                $sesi->gantiId();
            }

            if ($request->getMethod() === 'GET') {
                $uri    = $request->getUri();
                $tujuan = $uri->getPath() . ($uri->getQuery() !== '' ? '?' . $uri->getQuery() : '');
                if (self::tujuanSah($tujuan, $this->pengaturan)) {
                    $sesi->simpan(self::SESI_TUJUAN, $tujuan);
                }
            }

            return new RedirectResponse($this->pengaturan->rute('/masuk'));
        }

        $sesi->simpan(self::SESI_TERAKHIR, time());

        return $handler->handle($request->withAttribute(User::class, $user));
    }

    /**
     * Tujuan hanya boleh alamat di dalam dashboard ini, tanpa host lain (mencegah open redirect),
     * dan bukan halaman masuk atau keluar.
     */
    public static function tujuanSah(mixed $tujuan, Pengaturan $pengaturan): bool
    {
        if (! is_string($tujuan) || $tujuan === '' || strlen($tujuan) > 2000 || $tujuan[0] !== '/') {
            return false;
        }

        if (str_starts_with($tujuan, '//') || str_contains($tujuan, '\\') || preg_match('/[\x00-\x1f\x7f]/', $tujuan) === 1) {
            return false;
        }

        $jalur = (string) parse_url($tujuan, PHP_URL_PATH);
        $dasar = $pengaturan->alamatDasar;
        if ($dasar !== '' && $jalur !== $dasar && ! str_starts_with($jalur, $dasar . '/')) {
            return false;
        }

        return ! in_array($jalur, [$pengaturan->rute('/masuk'), $pengaturan->rute('/keluar')], true);
    }
}
