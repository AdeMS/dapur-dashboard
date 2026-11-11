<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\MultiWebsite\Bootstrap;
use AdeMS\Dapur\MultiWebsite\PencariWebsite;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Daftar website yang terdaftar. Hanya membaca, tidak mengubah apa pun. */
final class WebsiteHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Tampilan $tampilan,
        private readonly string $folderInti,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $daftar = class_exists(Bootstrap::class) ? Bootstrap::bacaDaftar($this->folderInti) : null;

        return $this->tampilan->halaman('dashboard::website', $request, [
            'multiWebsiteAktif' => $daftar !== null,
            'website'           => $daftar !== null ? $this->baris($daftar) : [],
        ]);
    }

    /**
     * @param array<mixed> $daftar
     * @return list<array{nama: string, alias: list<string>, mode: string, tersedia: bool}>
     */
    private function baris(array $daftar): array
    {
        $terdaftar = is_array($daftar['website'] ?? null) ? $daftar['website'] : [];
        $baris     = [];

        foreach ((new PencariWebsite($daftar, $this->folderInti))->semua() as $nama => $website) {
            $data    = is_array($terdaftar[$nama] ?? null) ? $terdaftar[$nama] : [];
            $alias   = is_array($data['alias'] ?? null) ? array_values(array_filter($data['alias'], 'is_string')) : [];
            $baris[] = [
                'nama'     => $nama,
                'alias'    => $alias,
                'mode'     => is_string($data['lokasi'] ?? null) && $data['lokasi'] !== '' ? 'Folder domain' : 'Terpusat',
                'tersedia' => $website !== null,
            ];
        }

        return $baris;
    }
}
