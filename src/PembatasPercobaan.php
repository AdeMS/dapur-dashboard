<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\ResultSet\ResultSetInterface;

/**
 * Membatasi percobaan login yang gagal dalam 15 menit terakhir.
 *
 * Batas per akun dari satu IP dibuat kecil. Batas per akun dari semua IP dibuat jauh lebih longgar,
 * agar orang lain tidak mudah mengunci pemilik akun dengan sengaja gagal login.
 */
final class PembatasPercobaan
{
    public const JENDELA_DETIK  = 900;
    public const BATAS_AKUN_IP  = 5;
    public const BATAS_IP       = 20;
    public const BATAS_AKUN     = 30;

    public function __construct(
        private readonly Adapter $db,
    ) {
    }

    public function terkunci(string $email, string $ip): bool
    {
        $kunci = self::kunci($email);

        return $this->hitung('kunci = ? AND ip = ?', [$kunci, $ip]) >= self::BATAS_AKUN_IP
            || $this->hitung('ip = ?', [$ip]) >= self::BATAS_IP
            || $this->hitung('kunci = ?', [$kunci]) >= self::BATAS_AKUN;
    }

    public function catat(string $email, string $ip): void
    {
        $this->db->query('DELETE FROM adems_percobaan_masuk WHERE waktu < ?', [$this->batas(86400)]);
        $this->db->query(
            'INSERT INTO adems_percobaan_masuk (kunci, ip, waktu) VALUES (?, ?, ?)',
            [self::kunci($email), $ip, gmdate('Y-m-d H:i:s')],
        );
    }

    /** Menghapus catatan akun ini dari IP ini setelah login berhasil. */
    public function bersihkan(string $email, string $ip): void
    {
        $this->db->query('DELETE FROM adems_percobaan_masuk WHERE kunci = ? AND ip = ?', [self::kunci($email), $ip]);
    }

    /** Email tidak disimpan apa adanya, karena orang kadang salah mengetik password di kolom email. */
    private static function kunci(string $email): string
    {
        return hash('sha256', DaftarUser::normalkanEmail($email));
    }

    private function batas(int $detik): string
    {
        return gmdate('Y-m-d H:i:s', time() - $detik);
    }

    /** @param list<string> $parameter */
    private function hitung(string $syarat, array $parameter): int
    {
        $hasil = $this->db->query(
            "SELECT COUNT(*) AS jumlah FROM adems_percobaan_masuk WHERE {$syarat} AND waktu >= ?",
            [...$parameter, $this->batas(self::JENDELA_DETIK)],
        );

        return $hasil instanceof ResultSetInterface ? (int) ($hasil->toArray()[0]['jumlah'] ?? 0) : 0;
    }
}
