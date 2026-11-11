<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\Sesi;
use AdeMS\Dapur\Dashboard\Skema;
use Laminas\Db\Adapter\Adapter;

/** Sesi di memori, untuk uji. */
final class SesiLarik implements Sesi
{
    /** @var array<string, mixed> */
    public array $isi = [];
    public int $gantiId = 0;
    public bool $hancur = false;

    public function ambil(string $kunci): mixed
    {
        return $this->isi[$kunci] ?? null;
    }

    public function simpan(string $kunci, mixed $nilai): void
    {
        $this->isi[$kunci] = $nilai;
    }

    public function hapus(string $kunci): void
    {
        unset($this->isi[$kunci]);
    }

    public function gantiId(): void
    {
        $this->gantiId++;
    }

    public function hancurkan(): void
    {
        $this->isi    = [];
        $this->hancur = true;
    }
}

final class DatabaseUji
{
    public static function baru(): Adapter
    {
        $db = new Adapter(['driver' => 'Pdo_Sqlite', 'database' => ':memory:']);
        foreach (Skema::perintah('SQLite') as $sql) {
            $db->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        return $db;
    }
}
