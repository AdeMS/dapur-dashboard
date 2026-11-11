<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

/** Memeriksa email dan kata sandi. */
final class Autentikasi
{
    /** Hash tiruan, agar waktu proses email tak terdaftar sama dengan email terdaftar. */
    private const HASH_TIRUAN = '$2y$12$IkUn9.GODEFjhaYLMl0rP.JQU8VUUFf8ij62P7.ZbhkZmx5DHCDQ6';

    private const PANJANG_EMAIL_MAKS = 190;
    private const PANJANG_SANDI_MAKS = 1024;

    public function __construct(
        private readonly DaftarUser $user,
        private readonly PembatasPercobaan $pembatas,
    ) {
    }

    public function masuk(string $email, string $kataSandi, string $ip, string $lingkup): HasilMasuk
    {
        $email = DaftarUser::normalkanEmail($email);

        if ($this->pembatas->terkunci($email, $ip)) {
            return new HasilMasuk(HasilMasuk::DIBATASI);
        }

        $bentukSah = $email !== '' && strlen($email) <= self::PANJANG_EMAIL_MAKS
            && $kataSandi !== '' && strlen($kataSandi) <= self::PANJANG_SANDI_MAKS;
        $user      = $bentukSah ? $this->user->cariEmail($email) : null;
        $cocok     = password_verify($bentukSah ? $kataSandi : '', $user?->kataSandi ?? self::HASH_TIRUAN);

        // Semua penyebab gagal diperlakukan sama, agar keberadaan akun tidak bisa ditebak.
        if ($user === null || ! $cocok || ! $user->aktif || ! $this->user->punyaAkses($user->id, $lingkup)) {
            $this->pembatas->catat($email, $ip);

            return new HasilMasuk(HasilMasuk::GAGAL);
        }

        if (DaftarUser::perluHashBaru($user->kataSandi)) {
            $this->user->gantiSandi($user->id, $kataSandi);
        }

        $this->pembatas->bersihkan($email, $ip);
        $this->user->catatMasuk($user->id);

        return new HasilMasuk(HasilMasuk::BERHASIL, $user);
    }
}
