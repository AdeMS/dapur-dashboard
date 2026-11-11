<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;

/** Akses data user dan hak aksesnya per lingkup. */
final class DaftarUser
{
    public const PERAN_ADMIN = 'admin';

    public function __construct(
        private readonly Adapter $db,
    ) {
    }

    public function cariEmail(string $email): ?User
    {
        return $this->user('SELECT * FROM adems_user WHERE email = ?', [self::normalkanEmail($email)]);
    }

    public function cariId(int $id): ?User
    {
        return $this->user('SELECT * FROM adems_user WHERE id = ?', [$id]);
    }

    public function buat(string $email, string $nama, string $kataSandi): int
    {
        $this->jalankan(
            'INSERT INTO adems_user (email, nama, kata_sandi, aktif, dibuat_pada) VALUES (?, ?, ?, 1, ?)',
            [self::normalkanEmail($email), $nama, self::hash($kataSandi), gmdate('Y-m-d H:i:s')],
        );

        return (int) $this->db->getDriver()->getLastGeneratedValue();
    }

    public function gantiSandi(int $id, string $kataSandi): void
    {
        $this->jalankan('UPDATE adems_user SET kata_sandi = ? WHERE id = ?', [self::hash($kataSandi), $id]);
    }

    public function catatMasuk(int $id): void
    {
        $this->jalankan('UPDATE adems_user SET terakhir_masuk = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $id]);
    }

    public function punyaAkses(int $id, string $lingkup): bool
    {
        $hasil = $this->jalankan('SELECT 1 FROM adems_user_akses WHERE user_id = ? AND lingkup = ?', [$id, $lingkup]);

        return $hasil instanceof ResultSetInterface && $hasil->count() > 0;
    }

    /** Apakah user ini admin di salah satu lingkup; menentukan syarat kata sandinya. */
    public function adalahAdmin(int $id): bool
    {
        $hasil = $this->jalankan('SELECT 1 FROM adems_user_akses WHERE user_id = ? AND peran = ?', [$id, self::PERAN_ADMIN]);

        return $hasil instanceof ResultSetInterface && $hasil->count() > 0;
    }

    public function tambahAkses(int $id, string $lingkup, string $peran = self::PERAN_ADMIN): void
    {
        if (! $this->punyaAkses($id, $lingkup)) {
            $this->jalankan('INSERT INTO adems_user_akses (user_id, lingkup, peran) VALUES (?, ?, ?)', [$id, $lingkup, $peran]);
        }
    }

    public static function normalkanEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function hash(string $kataSandi): string
    {
        return password_hash($kataSandi, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function perluHashBaru(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** @param list<mixed> $parameter */
    private function user(string $sql, array $parameter): ?User
    {
        $hasil = $this->jalankan($sql, $parameter);
        $baris = $hasil instanceof ResultSetInterface ? $hasil->toArray() : [];
        if ($baris === []) {
            return null;
        }

        return new User(
            (int) $baris[0]['id'],
            (string) $baris[0]['email'],
            (string) $baris[0]['nama'],
            (string) $baris[0]['kata_sandi'],
            (int) $baris[0]['aktif'] === 1,
        );
    }

    /** @param list<mixed> $parameter */
    private function jalankan(string $sql, array $parameter): mixed
    {
        return $this->db->query($sql, $parameter);
    }
}
