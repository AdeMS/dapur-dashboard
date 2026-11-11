<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\MultiWebsite\Website;
use Composer\InstalledVersions;

/** Tempat dashboard sedang berjalan: inti Dapur (dashboard utama) atau salah satu website. */
final class Konteks
{
    /** Lingkup akses untuk dashboard utama. Lingkup dashboard website adalah nama domainnya. */
    public const LINGKUP_UTAMA = 'dashboard';

    /** Domain website yang sedang dilayani, atau null bila berjalan di inti. */
    public static function websiteAktif(): ?string
    {
        return class_exists(Website::class) ? Website::aktif()?->host : null;
    }

    public static function lingkup(): string
    {
        return self::websiteAktif() ?? self::LINGKUP_UTAMA;
    }

    public static function folderInti(): string
    {
        $folder = InstalledVersions::getRootPackage()['install_path'];

        return rtrim((string) (realpath($folder) ?: $folder), '/');
    }

    /**
     * Folder penyimpanan session. Berada di dalam data/cache karena folder itu sudah bisa ditulis web
     * server dan tertutup untuk akun lain; penghapusan cache konfigurasi tidak menyentuhnya.
     */
    public static function folderSesi(): string
    {
        $data = class_exists(Website::class) && Website::aktif() !== null
            ? Website::aktif()->folderData
            : self::folderInti() . '/data';

        return $data . '/cache/session';
    }
}
