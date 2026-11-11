<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use RuntimeException;

/**
 * Struktur tabel dashboard.
 *
 * adems_user_akses memuat hak akses per lingkup ('dashboard' atau nama domain website),
 * sehingga user yang sama nantinya bisa diberi akses ke beberapa website.
 */
final class Skema
{
    /** @return list<string> */
    public static function perintah(string $platform): array
    {
        return match ($platform) {
            'MySQL'  => self::mysql(),
            'SQLite' => self::sqlite(),
            default  => throw new RuntimeException("Dashboard belum mendukung database {$platform}."),
        };
    }

    /** @return list<string> */
    private static function mysql(): array
    {
        $opsi = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        return [
            "CREATE TABLE IF NOT EXISTS adems_user (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                nama VARCHAR(190) NOT NULL,
                kata_sandi VARCHAR(255) NOT NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                dibuat_pada DATETIME NOT NULL,
                terakhir_masuk DATETIME NULL,
                UNIQUE KEY uq_adems_user_email (email)
            ) {$opsi}",
            "CREATE TABLE IF NOT EXISTS adems_user_akses (
                user_id BIGINT UNSIGNED NOT NULL,
                lingkup VARCHAR(190) NOT NULL,
                peran VARCHAR(50) NOT NULL,
                PRIMARY KEY (user_id, lingkup),
                CONSTRAINT fk_adems_user_akses_user FOREIGN KEY (user_id) REFERENCES adems_user (id) ON DELETE CASCADE
            ) {$opsi}",
            "CREATE TABLE IF NOT EXISTS adems_percobaan_masuk (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                kunci CHAR(64) NOT NULL,
                ip VARCHAR(45) NOT NULL,
                waktu DATETIME NOT NULL,
                KEY idx_adems_percobaan_kunci (kunci, ip, waktu),
                KEY idx_adems_percobaan_ip (ip, waktu),
                KEY idx_adems_percobaan_waktu (waktu)
            ) {$opsi}",
        ];
    }

    /** @return list<string> */
    private static function sqlite(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS adems_user (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                nama TEXT NOT NULL,
                kata_sandi TEXT NOT NULL,
                aktif INTEGER NOT NULL DEFAULT 1,
                dibuat_pada TEXT NOT NULL,
                terakhir_masuk TEXT NULL
            )',
            'CREATE TABLE IF NOT EXISTS adems_user_akses (
                user_id INTEGER NOT NULL REFERENCES adems_user (id) ON DELETE CASCADE,
                lingkup TEXT NOT NULL,
                peran TEXT NOT NULL,
                PRIMARY KEY (user_id, lingkup)
            )',
            'CREATE TABLE IF NOT EXISTS adems_percobaan_masuk (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                kunci TEXT NOT NULL,
                ip TEXT NOT NULL,
                waktu TEXT NOT NULL
            )',
        ];
    }
}
