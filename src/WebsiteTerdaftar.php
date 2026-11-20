<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use AdeMS\Dapur\MultiWebsite\Bootstrap;
use AdeMS\Dapur\MultiWebsite\PencariWebsite;
use AdeMS\Dapur\User\BatasanDomain;
use AdeMS\Dapur\User\DaftarWebsite;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\ConfigAggregator\PhpFileProvider;

/**
 * Website terdaftar di adems/dapur-multiwebsite, untuk halaman User di dashboard utama: admin super bisa memberi
 * akses website kepada user dari sana.
 *
 * Batasan domain email dibaca dari config/autoload website itu (`'user' => ['domain_email' => [...]]`), file yang
 * sama yang dimuat saat website itu dibuka. Bila website tidak mengaturnya, berlaku pengaturan inti, karena
 * config/autoload inti juga dimuat untuk semua website.
 */
final class WebsiteTerdaftar implements DaftarWebsite
{
    /** @var list<string>|null */
    private ?array $website = null;

    /** @var array<string, \AdeMS\Dapur\MultiWebsite\Website|null> */
    private array $folder = [];

    /** @var array<string, BatasanDomain> */
    private array $batasan = [];

    public function __construct(
        private readonly string $folderInti,
        private readonly BatasanDomain $batasanInti = new BatasanDomain(),
    ) {
    }

    /** Hanya bila paket multi-website terpasang dan daftar websitenya ada. */
    public static function tersedia(string $folderInti): bool
    {
        return class_exists(Bootstrap::class) && Bootstrap::bacaDaftar($folderInti) !== null;
    }

    public function website(): array
    {
        if ($this->website === null) {
            $daftar        = class_exists(Bootstrap::class) ? Bootstrap::bacaDaftar($this->folderInti) : null;
            $this->folder  = $daftar === null ? [] : (new PencariWebsite($daftar, $this->folderInti))->semua();
            $this->website = array_keys(array_filter($this->folder, static fn ($w): bool => $w !== null));
            sort($this->website);
        }

        return $this->website;
    }

    public function batasanDomain(string $host): BatasanDomain
    {
        if (! isset($this->batasan[$host])) {
            $this->website();
            $website = $this->folder[$host] ?? null;
            if ($website === null) {
                throw new \RuntimeException("Website {$host} tidak terdaftar atau foldernya tidak ada.");
            }

            $config = (new ConfigAggregator([
                new PhpFileProvider($website->folderConfig . '/autoload/{{,*.}global,{,*.}local}.php'),
            ]))->getMergedConfig();
            $user   = is_array($config['user'] ?? null) ? $config['user'] : [];

            $this->batasan[$host] = array_key_exists('domain_email', $user)
                ? BatasanDomain::dariPengaturan($user['domain_email'])
                : $this->batasanInti;
        }

        return $this->batasan[$host];
    }
}
