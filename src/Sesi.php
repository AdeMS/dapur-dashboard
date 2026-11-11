<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

/** Data session pengunjung. Tersedia sebagai atribut request bernama Sesi::class. */
interface Sesi
{
    public function ambil(string $kunci): mixed;

    public function simpan(string $kunci, mixed $nilai): void;

    public function hapus(string $kunci): void;

    /** Mengganti ID session sambil mempertahankan isinya; wajib dipanggil saat login. */
    public function gantiId(): void;

    /** Menghapus seluruh session. */
    public function hancurkan(): void;
}
