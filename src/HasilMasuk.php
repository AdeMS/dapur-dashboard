<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

final class HasilMasuk
{
    public const BERHASIL = 'berhasil';
    public const GAGAL    = 'gagal';
    public const DIBATASI = 'dibatasi';

    public function __construct(
        public readonly string $status,
        public readonly ?User $user = null,
    ) {
    }
}
