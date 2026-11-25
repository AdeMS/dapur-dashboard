<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Asset\Aset;
use AdeMS\Dapur\Asset\DaftarAset;
use AdeMS\Dapur\Asset\GagalAset;
use AdeMS\Dapur\Asset\JenisFile;
use AdeMS\Dapur\Asset\Katalog;
use AdeMS\Dapur\Asset\KelolaAset;
use AdeMS\Dapur\Asset\Lingkup;
use AdeMS\Dapur\Asset\Nama;
use AdeMS\Dapur\Asset\Pengaturan as PengaturanAset;
use AdeMS\Dapur\Asset\Penyimpanan;
use AdeMS\Dapur\Asset\Unggahan;
use AdeMS\Dapur\Dashboard\Tampilan;
use AdeMS\Dapur\Dashboard\WebsiteTerdaftar;
use AdeMS\Dapur\User\DaftarUser;
use AdeMS\Dapur\User\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\User\Sesi;
use AdeMS\Dapur\User\TokenCsrf;
use AdeMS\Dapur\User\User;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Halaman Aset di dashboard utama (khusus admin super): daftar aset, membuat aset, versi (buat, terbitkan, hapus,
 * versi bawaan, versi per website), pengelola file, unggah bertahap, dan riwayat.
 *
 * Alamat: /aset (daftar), /aset?nama=<vendor/aplikasi>[&versi=<versi>][&folder=<jalur>] (satu aset).
 */
final class AsetHandler implements RequestHandlerInterface
{
    public const SESI_PESAN = 'dashboard.aset.pesan';
    public const SESI_GALAT = 'dashboard.aset.galat';

    public function __construct(
        private readonly Tampilan $tampilan,
        private readonly Katalog $katalog,
        private readonly ?DaftarAset $daftar,
        private readonly PengaturanAset $pengaturan,
        private readonly Unggahan $unggahan,
        private readonly ?WebsiteTerdaftar $website,
        private readonly DateTimeZone $zonaWaktu,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user  = $request->getAttribute(User::class);
        $peran = $request->getAttribute(WajibMasukMiddleware::ATRIBUT_PERAN);
        assert($user instanceof User);

        if ($peran !== DaftarUser::PERAN_ADMIN_SUPER) {
            return $request->getMethod() === 'POST'
                ? new EmptyResponse(403)
                : $this->tampilan->halaman('dashboard::aset', $request, ['ditolak' => true] + $this->kosong(), 403);
        }

        $siap = $this->daftar !== null && $this->daftar->tersedia();
        if ($request->getMethod() === 'POST') {
            return $siap ? $this->kirim($request, $user) : new EmptyResponse(503);
        }

        $sesi  = $request->getAttribute(Sesi::class);
        assert($sesi instanceof Sesi);
        $pesan = $sesi->ambil(self::SESI_PESAN);
        $galat = $sesi->ambil(self::SESI_GALAT);
        $sesi->hapus(self::SESI_PESAN);
        $sesi->hapus(self::SESI_GALAT);

        $q    = $request->getQueryParams();
        $nama = is_string($q['nama'] ?? null) ? $q['nama'] : null;
        $data = [
            'pesan'          => is_string($pesan) ? $pesan : null,
            'galat'          => is_string($galat) ? $galat : null,
            'belumSiap'      => ! $siap,
            'folderMasalah'  => $this->masalahFolder(),
            'zipTersedia'    => class_exists(\ZipArchive::class),
            'jenisFile'      => JenisFile::teks(),
            'ukuranPotongan' => Unggahan::POTONGAN,
            'unggahMaks'     => $this->pengaturan->unggahMaks,
            'unggahMaksTeks' => Penyimpanan::ukuranTeks($this->pengaturan->unggahMaks),
        ];

        if ($nama === null) {
            $cari = is_string($q['cari'] ?? null) ? mb_substr(trim($q['cari']), 0, 100) : '';

            return $this->tampilan->halaman('dashboard::aset', $request, $data + $this->daftarAset($cari) + $this->kosong());
        }

        $aset = $this->katalog->cari($nama);
        if ($aset === null) {
            return $this->tampilan->halaman('dashboard::aset', $request, ['tidakAda' => $nama] + $data + $this->kosong(), 404);
        }

        return $this->tampilan->halaman('dashboard::aset', $request, $data + $this->detail($aset, $q) + $this->kosong());
    }

    /** @return array<string, mixed> */
    private function daftarAset(string $cari = ''): array
    {
        $baris = [];
        $semua = $this->katalog->semua();
        foreach ($semua as $aset) {
            if ($cari !== '' && mb_stripos($aset->nama . ' ' . $aset->keterangan . ' ' . ($aset->paket ?? ''), $cari) === false) {
                continue;
            }

            $baris[] = [
                'nama'       => $aset->nama,
                'sumber'     => $aset->sumber,
                'sumberTeks' => KelolaAset::sumberTeks($aset),
                'akses'      => $aset->akses,
                'aktif'      => $aset->aktif,
                'berversi'   => $aset->berversi,
                'versi'      => $aset->berversi ? $this->katalog->versiUntuk($aset) : null,
                'jumlahVersi' => count($aset->versi),
                'keterangan' => $aset->keterangan,
            ];
        }

        return ['mode' => 'daftar', 'semuaAset' => $baris, 'cari' => $cari, 'jumlahAset' => count($semua)];
    }

    /**
     * @param array<string, mixed> $q
     * @return array<string, mixed>
     */
    private function detail(Aset $aset, array $q): array
    {
        $versiBaris = [];
        $pilihan    = $this->daftar !== null && $aset->id !== null ? $this->daftar->pilihanAset($aset->id) : [];
        foreach (Nama::urutkanTerbaru(array_keys($aset->versi)) as $v) {
            $versi        = $aset->versi[$v];
            $versiBaris[] = [
                'versi'      => $v,
                'terbit'     => $versi->terbit,
                'sumber'     => $versi->sumber,
                'bisaUbah'   => $versi->dapatDiubah(),
                'bawaan'     => $aset->versiBawaan === $v,
                'dipakai'    => array_keys(array_filter($pilihan, static fn (string $p): bool => $p === $v)),
                'ringkasan'  => (new Penyimpanan($versi->folder))->ringkasan(),
            ];
        }

        // Versi yang isinya ditampilkan: pilihan di alamat, versi pertama yang belum terbit, atau versi yang dipakai.
        $versiDipilih = null;
        if ($aset->berversi) {
            $minta = is_string($q['versi'] ?? null) ? $q['versi'] : null;
            $draf  = array_values(array_filter($versiBaris, static fn (array $b): bool => $b['bisaUbah']));
            $versiDipilih = $minta !== null && isset($aset->versi[$minta])
                ? $minta
                : ($draf[0]['versi'] ?? $this->katalog->versiUntuk($aset) ?? ($versiBaris[0]['versi'] ?? null));
        }

        $folderIsi = $aset->folderIsi($versiDipilih);
        $bisaUbah  = $aset->berversi
            ? ($versiDipilih !== null && $aset->versi[$versiDipilih]->dapatDiubah())
            : $aset->isiDapatDiubah();
        $folder = '';
        $isi    = [];
        $galatIsi = null;
        if ($folderIsi !== null) {
            try {
                $folder = Nama::periksaJalur(is_string($q['folder'] ?? null) ? $q['folder'] : '');
                $isi    = is_dir($folderIsi) ? (new Penyimpanan($folderIsi))->daftar($folder) : [];
            } catch (GagalAset | InvalidArgumentException $e) {
                $galatIsi = $e->getMessage();
                $folder   = '';
            }
        }

        // Alamat publik file: hanya untuk versi terbit (atau aset tanpa versi).
        $terbuka = ! $aset->berversi || ($versiDipilih !== null && $aset->versi[$versiDipilih]->terbit);
        $dasarUrl = '/asset/' . $aset->nama . '/' . ($aset->berversi && $versiDipilih !== null ? rawurlencode($versiDipilih) . '/' : '');
        foreach ($isi as &$f) {
            $f['ukuranTeks'] = $f['folder'] ? '' : Penyimpanan::ukuranTeks($f['ukuran']);
            $f['diubahTeks'] = (new DateTimeImmutable('@' . $f['diubah']))->setTimezone($this->zonaWaktu)->format('Y-m-d H:i');
            $f['url']        = $terbuka && ! $f['folder'] ? $dasarUrl . implode('/', array_map('rawurlencode', explode('/', $f['jalur']))) : null;
        }
        unset($f);

        return [
            'mode'          => 'aset',
            'aset'          => $aset,
            'sumberTeks'    => KelolaAset::sumberTeks($aset),
            'versiBaris'    => $versiBaris,
            'versiTerbit'   => $aset->versiTerbit(),
            'versiDipakai'  => $this->katalog->versiUntuk($aset),
            'versiDipilih'  => $versiDipilih,
            'website'       => $aset->berversi && $aset->sumber !== Aset::SUMBER_FILE ? $this->pilihanWebsite($aset, $pilihan) : [],
            'folder'        => $folder,
            'isi'           => $isi,
            'galatIsi'      => $galatIsi,
            'bisaUbahIsi'   => $bisaUbah && $folderIsi !== null,
            'riwayat'       => $this->riwayat($aset->nama),
        ];
    }

    /**
     * @param array<string, string> $pilihan
     * @return list<array{lingkup: string, pilihan: ?string, dipakai: ?string, peringatan: bool}>
     */
    private function pilihanWebsite(Aset $aset, array $pilihan): array
    {
        $lingkup = [Lingkup::UTAMA, ...($this->website?->website() ?? [])];
        $bawaan  = $aset->versiBawaan ?? ($aset->versiTerbit()[0] ?? null);
        $hasil   = [];
        foreach ($lingkup as $l) {
            $dipakai = $this->katalog->versiUntuk($aset, $l);
            $hasil[] = [
                'lingkup'    => $l,
                'pilihan'    => $pilihan[$l] ?? null,
                'dipakai'    => $dipakai,
                // Versi mayor berbeda dari versi bawaan (mis. Bootstrap 6 vs 5): tampilan Dapur bisa berantakan.
                'peringatan' => $dipakai !== null && $bawaan !== null && self::mayor($dipakai) !== self::mayor($bawaan),
            ];
        }

        return $hasil;
    }

    private static function mayor(string $versi): string
    {
        return explode('.', $versi)[0];
    }

    /** @return list<array<string, mixed>> */
    private function riwayat(string $nama): array
    {
        if ($this->daftar === null || ! $this->daftar->tersedia()) {
            return [];
        }

        $hasil = [];
        foreach ($this->daftar->riwayat($nama, 20) as $b) {
            $hasil[] = [
                'waktu'      => (new DateTimeImmutable((string) $b['waktu'], new DateTimeZone('UTC')))->setTimezone($this->zonaWaktu)->format('Y-m-d H:i'),
                'peristiwa'  => DaftarAset::LABEL[$b['peristiwa']] ?? (string) $b['peristiwa'],
                'keterangan' => (string) ($b['keterangan'] ?? ''),
                'pelaku'     => (string) ($b['pelaku'] ?? ''),
            ];
        }

        return $hasil;
    }

    /** Masalah folder aset yang perlu diperbaiki di server (bukan dari dashboard); null bila siap. */
    private function masalahFolder(): ?string
    {
        $folder = $this->pengaturan->folder;
        if (! is_dir($folder)) {
            return "Folder aset belum ada: buat folder data/asset di inti Dapur, lalu beri hak tulis untuk web server (grup web server, izin 2770).";
        }

        return is_writable($folder) ? null : 'Folder aset (data/asset) belum bisa ditulis web server: beri hak tulis untuk grup web server (izin 2770).';
    }

    private function kirim(ServerRequestInterface $request, User $user): ResponseInterface
    {
        $sesi = $request->getAttribute(Sesi::class);
        $isi  = (array) $request->getParsedBody();
        $aksi = is_string($isi['aksi'] ?? null) ? $isi['aksi'] : '';
        $json = str_starts_with($aksi, 'unggah_');
        assert($sesi instanceof Sesi);

        if (! (new TokenCsrf($sesi))->sah($isi[TokenCsrf::FIELD] ?? null)) {
            return $json
                ? new JsonResponse(['galat' => 'Halaman sudah kedaluwarsa. Muat ulang halaman.'], 400)
                : new EmptyResponse(400);
        }

        $kelola = new KelolaAset($this->katalog, $this->daftar, $this->pengaturan, $user->id, $user->email);
        $nama   = is_string($isi['nama'] ?? null) ? $isi['nama'] : '';
        $versi  = is_string($isi['versi'] ?? null) && $isi['versi'] !== '' ? $isi['versi'] : null;
        $folder = is_string($isi['folder'] ?? null) ? $isi['folder'] : '';
        $teks   = static fn (string $k): string => is_string($isi[$k] ?? null) ? trim($isi[$k]) : '';

        if ($json) {
            return $this->unggah($request, $kelola, $user, $aksi, $isi);
        }

        $kembali = ['nama' => $nama, 'versi' => $versi, 'folder' => $folder];
        try {
            $pesan = match ($aksi) {
                'tambah' => (function () use ($kelola, $teks, &$kembali): string {
                    $aset = $kelola->tambah($teks('nama_baru'), $teks('berversi') === '1', $teks('akses'), $teks('keterangan'));
                    $kembali = ['nama' => $aset->nama];

                    return "Aset {$aset->nama} dibuat." . ($aset->berversi ? ' Buat versi pertamanya, unggah file, lalu terbitkan.' : ' Unggah filenya di bawah.');
                })(),
                'ubah' => (function () use ($kelola, $nama, $teks): string {
                    $kelola->ubah($nama, $teks('akses'), $teks('aktif') === '1', $teks('keterangan'));

                    return 'Pengaturan aset disimpan.';
                })(),
                'hapus' => (function () use ($kelola, $nama, $teks, &$kembali): string {
                    if ($teks('konfirmasi') !== $nama) {
                        throw new GagalAset('Ketik nama aset dengan benar untuk menghapusnya.');
                    }
                    $kelola->hapus($nama);
                    $kembali = [];

                    return "Aset {$nama} dan semua filenya dihapus.";
                })(),
                'versi_baru' => (function () use ($kelola, $nama, $teks, &$kembali): string {
                    $aset = $kelola->tambahVersi($nama, $teks('versi_baru'), $teks('salin_dari'));
                    $baru = Nama::normalkanVersi($teks('versi_baru'));
                    $kembali = ['nama' => $aset->nama, 'versi' => $baru];

                    return "Versi {$baru} dibuat. Unggah filenya, lalu terbitkan.";
                })(),
                'terbitkan' => (function () use ($kelola, $nama, $versi): string {
                    $kelola->terbitkan($nama, (string) $versi);

                    return "Versi {$versi} diterbitkan. Isinya sekarang terkunci.";
                })(),
                'hapus_versi' => (function () use ($kelola, $nama, $versi, &$kembali): string {
                    $kelola->hapusVersi($nama, (string) $versi);
                    $kembali = ['nama' => $nama];

                    return "Versi {$versi} dihapus.";
                })(),
                'versi_bawaan' => (function () use ($kelola, $nama, $versi): string {
                    $kelola->aturVersiBawaan($nama, $versi);

                    return 'Versi bawaan sekarang ' . ($versi ?? 'versi terbaru') . '.';
                })(),
                'pilih_versi' => (function () use ($kelola, $nama, $teks): string {
                    $lingkup = $teks('lingkup');
                    if (! $this->lingkupSah($lingkup)) {
                        throw new GagalAset('Website tidak dikenal.');
                    }
                    $pilih = $teks('versi_website') === '' ? null : $teks('versi_website');
                    $kelola->pilihVersi($nama, $lingkup, $pilih);

                    return sprintf('%s sekarang memakai %s.', $lingkup === Lingkup::UTAMA ? 'Dashboard utama' : $lingkup, $pilih === null ? 'versi bawaan' : "versi {$pilih}");
                })(),
                'buat_folder' => (function () use ($kelola, $nama, $versi, $folder, $teks, &$kembali): string {
                    $hasil = $kelola->buatFolder($nama, $versi, ltrim($folder . '/' . $teks('nama_folder'), '/'));

                    return "Folder /{$hasil} dibuat.";
                })(),
                'ganti_nama' => (function () use ($kelola, $nama, $versi, $teks): string {
                    $hasil = $kelola->gantiNama($nama, $versi, $teks('jalur'), $teks('nama_baru'));

                    return "Nama diganti menjadi /{$hasil}.";
                })(),
                'hapus_file' => (function () use ($kelola, $nama, $versi, $teks): string {
                    $kelola->hapusFile($nama, $versi, $teks('jalur'));

                    return '/' . $teks('jalur') . ' dihapus.';
                })(),
                default => throw new GagalAset('Aksi tidak dikenal.'),
            };
            $sesi->simpan(self::SESI_PESAN, $pesan);
        } catch (GagalAset $e) {
            $sesi->simpan(self::SESI_GALAT, $e->getMessage());
        }

        return new RedirectResponse($this->alamat($request, $kembali));
    }

    /**
     * Unggah bertahap (dipanggil JavaScript halaman ini): unggah_mulai, unggah_potongan, unggah_selesai, unggah_batal.
     *
     * @param array<string, mixed> $isi
     */
    private function unggah(ServerRequestInterface $request, KelolaAset $kelola, User $user, string $aksi, array $isi): ResponseInterface
    {
        $kode = is_string($isi['kode'] ?? null) ? $isi['kode'] : '';
        try {
            switch ($aksi) {
                case 'unggah_mulai':
                    $nama    = (string) ($isi['nama'] ?? '');
                    $versi   = is_string($isi['versi'] ?? null) && $isi['versi'] !== '' ? $isi['versi'] : null;
                    $bongkar = ($isi['bongkar'] ?? '') === '1';
                    $file    = (string) ($isi['file'] ?? '');
                    $ukuran  = (int) ($isi['ukuran'] ?? -1);

                    // Jalur relatif dari unggah folder (mis. dist/css/a.css) ikut menjadi subfolder tujuan.
                    $relatif = Nama::periksaJalur($file);
                    $subfolder = trim(dirname('/' . $relatif), '/');
                    $folder  = Nama::periksaJalur(implode('/', array_filter([Nama::periksaJalur((string) ($isi['folder'] ?? '')), $subfolder], static fn (string $b): bool => $b !== '')));
                    $namaFile = basename($relatif);
                    $zip     = $bongkar && JenisFile::akhiran($namaFile) === 'zip';
                    if ($namaFile === '' || (! $zip && ! JenisFile::diizinkan($namaFile))) {
                        throw new GagalAset("Jenis file tidak diizinkan: {$namaFile}.");
                    }

                    $kelola->penyimpanan($nama, $versi); // memastikan tujuan boleh diubah sebelum mulai

                    return new JsonResponse(['kode' => $this->unggahan->mulai($namaFile, $ukuran, $user->id, [
                        'nama' => $nama, 'versi' => (string) $versi, 'folder' => $folder, 'bongkar' => $zip,
                    ])]);

                case 'unggah_potongan':
                    $potongan = $request->getUploadedFiles()['potongan'] ?? null;
                    if (! $potongan instanceof UploadedFileInterface || $potongan->getError() !== UPLOAD_ERR_OK) {
                        throw new GagalAset('Potongan unggahan tidak diterima server.');
                    }

                    $diterima = $this->unggahan->potongan($kode, $user->id, (int) ($isi['nomor'] ?? -1), (string) $potongan->getStream());

                    return new JsonResponse(['diterima' => $diterima]);

                case 'unggah_selesai':
                    $hasil = $this->unggahan->selesai($kode, $user->id);
                    try {
                        $d     = $hasil['data'];
                        $pesan = $kelola->simpanUnggahan(
                            (string) $d['nama'],
                            ($d['versi'] ?? '') === '' ? null : (string) $d['versi'],
                            (string) ($d['folder'] ?? ''),
                            $hasil['nama'],
                            $hasil['file'],
                            ($d['bongkar'] ?? false) === true,
                        );
                    } finally {
                        $this->unggahan->buang($kode);
                    }

                    return new JsonResponse(['pesan' => $pesan]);

                case 'unggah_batal':
                    $this->unggahan->buang($kode);

                    return new JsonResponse(['pesan' => 'Unggahan dibatalkan.']);
            }

            throw new GagalAset('Aksi tidak dikenal.');
        } catch (GagalAset | InvalidArgumentException $e) {
            if ($aksi !== 'unggah_mulai') {
                $this->unggahan->buang($kode);
            }

            return new JsonResponse(['galat' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            $this->unggahan->buang($kode);
            throw $e;
        }
    }

    private function lingkupSah(string $lingkup): bool
    {
        return $lingkup === Lingkup::UTAMA || in_array($lingkup, $this->website?->website() ?? [], true);
    }

    /** @param array<string, ?string> $q */
    private function alamat(ServerRequestInterface $request, array $q): string
    {
        $q = array_filter($q, static fn (?string $v): bool => $v !== null && $v !== '');

        return $request->getUri()->getPath() . ($q === [] ? '' : '?' . http_build_query($q));
    }

    /** @return array<string, mixed> */
    private function kosong(): array
    {
        return [
            'ditolak'       => false,
            'tidakAda'      => null,
            'mode'          => 'daftar',
            'semuaAset'     => [],
            'aset'          => null,
            'pesan'         => null,
            'galat'         => null,
            'belumSiap'     => false,
            'folderMasalah' => null,
            'zipTersedia'   => false,
            'zonaWaktu'     => $this->zonaWaktu->getName(),
            'cari'          => '',
            'jumlahAset'    => 0,
        ];
    }
}
