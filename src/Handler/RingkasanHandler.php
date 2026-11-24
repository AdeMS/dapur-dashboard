<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\Database\Pemasang;
use AdeMS\Dapur\User\DaftarUser;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class RingkasanHandler implements RequestHandlerInterface
{
    /**
     * @param (Closure(): Pemasang)|null $pemasang dibuat hanya bila perlu, agar halaman tidak memeriksa database untuk
     *                                             user yang bukan admin super
     * @param string $perintahPasang perintah yang disarankan bila database perlu diperbarui
     */
    public function __construct(
        private readonly Tampilan $tampilan,
        private readonly ?Closure $pemasang = null,
        private readonly string $perintahPasang = 'php dapur pasang',
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->tampilan->halaman('dashboard::ringkasan', $request, [
            'database' => $this->periksaDatabase($request),
        ]);
    }

    /**
     * Untuk admin super: apakah tabel modul perlu dipasang atau diperbarui (`php dapur pasang`). Menggantikan pesan
     * "tabel belum dibuat" yang dulu muncul di masing-masing fitur.
     *
     * @return array{perlu: bool, perintah: string, galat: ?string}|null null bila tidak ditampilkan
     */
    private function periksaDatabase(ServerRequestInterface $request): ?array
    {
        if ($this->pemasang === null || $request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN) !== DaftarUser::PERAN_ADMIN_SUPER) {
            return null;
        }

        try {
            $perlu = ($this->pemasang)()->perluDiperbarui();
        } catch (Throwable $e) {
            error_log('dapur-dashboard: pemeriksaan database gagal: ' . $e->getMessage());

            return ['perlu' => true, 'perintah' => $this->perintahPasang, 'galat' => $e->getMessage()];
        }

        return ['perlu' => $perlu, 'perintah' => $this->perintahPasang, 'galat' => null];
    }
}
