<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

/** Syarat kata sandi baru: admin lebih ketat daripada user biasa. */
final class AturanKataSandi
{
    public const MINIMAL_ADMIN = 12;
    public const MINIMAL_USER  = 8;

    public static function panjangMinimal(bool $admin): int
    {
        return $admin ? self::MINIMAL_ADMIN : self::MINIMAL_USER;
    }
}
