<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Dashboard\Handler\PesanErrorHandler;
use AdeMS\Dapur\User\DaftarUser;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\User\PenyajiHalaman;
use AdeMS\Dapur\User\Sesi;
use AdeMS\Dapur\User\TokenCsrf;
use AdeMS\Dapur\User\User;
use DateTimeZone;
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

    /** Jumlah error terbaru di menu notifikasi. */
    public const JUMLAH_NOTIFIKASI = 5;

    public function __construct(
        private readonly TemplateRendererInterface $renderer,
        private readonly Pengaturan $pengaturan,
        private readonly ?PesanError $pesanError = null,
        private readonly DateTimeZone $zonaWaktu = new DateTimeZone('UTC'),
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

        $user  = $request->getAttribute(User::class);
        $peran = $request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN);

        $data += [
            'notifikasi'   => $user instanceof User ? $this->notifikasi($user, $peran) : null,
            'layout'       => $this->pengaturan->layout,
            'menu'         => $this->menu($request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN)),
            'ruteAktif'    => $request->getAttribute(RouteResult::class)?->getMatchedRouteName() ?: null,
            'user'         => $user,
            'alamatKeluar' => $this->pengaturan->rute('/keluar'),
            'alamatProfil' => $this->pengaturan->rute('/profil'),
            'peranUser'    => $peran,
            'tokenCsrf'    => (new TokenCsrf($sesi))->token(),
            'fieldCsrf'    => TokenCsrf::FIELD,
        ];

        // Halaman dirender tanpa layout, lalu dimasukkan ke kerangka dashboard (menu samping + menu user),
        // dan kerangka itulah yang memakai layout website.
        $isi = $this->renderer->render($template, ['layout' => false] + $data);

        return new HtmlResponse($this->renderer->render(self::KERANGKA, ['isi' => $isi] + $data), $status);
    }

    /**
     * Notifikasi Pesan Error untuk admin super dan admin web: jumlah error yang belum dibaca dan beberapa error
     * terbaru. Null untuk peran lain atau bila log error belum tersedia (Dapur sebelum 2.8).
     *
     * @return array{jumlah: int, terbaru: list<array<string, mixed>>, alamat: string}|null
     */
    private function notifikasi(User $user, mixed $peran): ?array
    {
        if ($this->pesanError === null || ! DaftarUser::peranAdmin(is_string($peran) ? $peran : null) || ! $this->pesanError->tersedia()) {
            return null;
        }

        $terbaru = [];
        foreach ($this->pesanError->terbaru($user->id, self::JUMLAH_NOTIFIKASI) as $e) {
            $terbaru[] = $e + ['waktuSetempat' => PesanErrorHandler::waktu((string) ($e['waktu'] ?? ''), $this->zonaWaktu)];
        }

        return [
            'jumlah'  => $this->pesanError->jumlahBelumDibaca($user->id),
            'terbaru' => $terbaru,
            'alamat'  => $this->pengaturan->rute('/error'),
        ];
    }

    /**
     * Menu yang boleh dilihat user ini: item dengan 'peran' hanya tampil untuk peran tersebut. Peran 'admin'
     * berarti admin mana pun (admin super atau admin web).
     *
     * @return list<array{label: string, rute: string, ikon: string, peran: ?string}>
     */
    private function menu(mixed $peran): array
    {
        return array_values(array_filter(
            $this->pengaturan->menu,
            static fn (array $item): bool => $item['peran'] === null
                || $item['peran'] === $peran
                || ($item['peran'] === 'admin' && DaftarUser::peranAdmin(is_string($peran) ? $peran : null)),
        ));
    }
}
