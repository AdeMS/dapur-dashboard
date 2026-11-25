<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\Asset\Aset;
use AdeMS\Dapur\Asset\PenjagaAkses;
use AdeMS\Dapur\User\DaftarUser;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Pemeriksa aset ber-akses "login" (adems/dapur-asset): pengunjung harus sedang login di website ini lewat dashboard
 * dan punya akses ke website ini. Session hanya dibaca (tidak diubah dan tidak memperpanjang waktu login), dan tidak
 * ada cookie baru yang dikirim.
 */
final class PenjagaAksesUser implements PenjagaAkses
{
    private const NAMA_COOKIE = 'dapur_sesi';

    public function __construct(
        private readonly DaftarUser $daftar,
        private readonly string $folderSesi,
        private readonly int $batasDiam,
        private readonly string $lingkup,
    ) {
    }

    public function boleh(ServerRequestInterface $request, Aset $aset): bool
    {
        $isi = $this->bacaSesi($request);
        $id  = $isi[WajibMasukMiddleware::SESI_USER] ?? null;
        $terakhir = $isi[WajibMasukMiddleware::SESI_TERAKHIR] ?? null;
        if (! is_int($id) || ! is_int($terakhir) || time() - $terakhir > $this->batasDiam) {
            return false;
        }

        try {
            $user = $this->daftar->cariId($id);
            if ($user === null || ! $user->aktif) {
                return false;
            }

            // Sesudah kata sandi diganti, session lama tidak berlaku lagi (sama seperti halaman dashboard).
            $sidik = $isi[WajibMasukMiddleware::SESI_SANDI] ?? null;
            if (is_string($sidik) && ! hash_equals($user->sidikSandi(), $sidik)) {
                return false;
            }

            return $this->daftar->peran($user->id, $this->lingkup) !== null;
        } catch (Throwable $e) {
            error_log('dashboard: akses aset tidak bisa diperiksa: ' . $e->getMessage());

            return false;
        }
    }

    /** @return array<string, mixed> */
    private function bacaSesi(ServerRequestInterface $request): array
    {
        $kode = $request->getCookieParams()[self::NAMA_COOKIE] ?? null;
        if (! is_string($kode) || preg_match('/^[A-Za-z0-9,-]{22,256}$/', $kode) !== 1 || ! is_dir($this->folderSesi)) {
            return [];
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            return session_id() === $kode ? $_SESSION : [];
        }

        session_save_path($this->folderSesi);
        session_name(self::NAMA_COOKIE);
        session_id($kode);
        try {
            if (! @session_start(['read_and_close' => true, 'use_cookies' => 0, 'use_strict_mode' => 1])) {
                return [];
            }

            $isi = $_SESSION;
        } finally {
            $_SESSION = [];
        }

        return is_array($isi) ? $isi : [];
    }
}
