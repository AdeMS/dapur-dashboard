<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

/** Sesi di atas session bawaan PHP; session harus sudah dimulai. */
final class SesiPhp implements Sesi
{
    public function ambil(string $kunci): mixed
    {
        return $_SESSION[$kunci] ?? null;
    }

    public function simpan(string $kunci, mixed $nilai): void
    {
        $_SESSION[$kunci] = $nilai;
    }

    public function hapus(string $kunci): void
    {
        unset($_SESSION[$kunci]);
    }

    public function gantiId(): void
    {
        session_regenerate_id(true);
    }

    public function hancurkan(): void
    {
        $_SESSION = [];

        $cookie = session_get_cookie_params();
        unset($cookie['lifetime']);
        setcookie(session_name() ?: '', '', ['expires' => 1] + $cookie);

        session_destroy();
    }
}
