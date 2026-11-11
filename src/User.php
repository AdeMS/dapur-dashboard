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
}
