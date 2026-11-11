<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

/** User dashboard. Tersedia sebagai atribut request bernama User::class setelah login. */
final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $nama,
        public readonly string $kataSandi,
        public readonly bool $aktif,
    ) {
    }

    /**
     * Inisial untuk avatar: huruf pertama nama depan dan nama belakang ("Ade M Saragih" menjadi "AS").
     * Nama satu kata memakai satu huruf; nama kosong memakai huruf pertama email.
     */
    public function inisial(): string
    {
        $kata = preg_split('/\s+/u', trim($this->nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($kata === []) {
            $kata = [$this->email];
        }

        $huruf = self::hurufPertama($kata[0]);
        if (count($kata) > 1) {
            $huruf .= self::hurufPertama($kata[count($kata) - 1]);
        }

        return function_exists('mb_strtoupper') ? mb_strtoupper($huruf, 'UTF-8') : strtoupper($huruf);
    }

    private static function hurufPertama(string $kata): string
    {
        return preg_match('/^\X/u', $kata, $cocok) === 1 ? $cocok[0] : '';
    }
}
