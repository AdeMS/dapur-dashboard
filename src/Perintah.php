<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use Closure;
use Laminas\Db\Adapter\Adapter;

/** Perintah baris: pasang, buat-admin, ganti-sandi. */
final class Perintah
{
    /** @param Closure(string): string $bacaSandi meminta kata sandi tanpa menampilkannya */
    public function __construct(
        private readonly Adapter $db,
        private readonly DaftarUser $user,
        private readonly Closure $bacaSandi,
        private readonly string $lingkup,
    ) {
    }

    /** @param list<string> $argumen tanpa nama program */
    public function jalankan(array $argumen): int
    {
        // --website=<domain> sudah dibaca paket multi-website sebelum perintah ini berjalan.
        $argumen = array_values(array_filter($argumen, static fn (string $a): bool => ! str_starts_with($a, '--website=')));
        $utama   = array_values(array_filter($argumen, static fn (string $a): bool => ! str_starts_with($a, '--')));

        return match ($utama[0] ?? '') {
            'pasang'      => $this->pasang(),
            'buat-admin'  => $this->buatAdmin($utama[1] ?? '', $this->opsi($argumen, 'nama')),
            'ganti-sandi' => $this->gantiSandi($utama[1] ?? ''),
            default       => $this->bantuan(($utama[0] ?? '') === '' ? 0 : 1),
        };
    }

    private function pasang(): int
    {
        foreach (Skema::perintah($this->db->getPlatform()->getName()) as $sql) {
            $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE);
        }

        echo 'Tabel dashboard sudah siap.' . PHP_EOL;

        return 0;
    }

    private function buatAdmin(string $email, ?string $nama): int
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->gagal('Pemakaian: dapur-dashboard buat-admin <email> [--nama="Nama Lengkap"]');
        }

        $ada = $this->user->cariEmail($email);
        if ($ada !== null) {
            $this->user->tambahAkses($ada->id, $this->lingkup);
            printf("User %s sudah ada. Akses ke '%s' dipastikan ada; kata sandinya tidak diubah.%s", $ada->email, $this->lingkup, PHP_EOL);

            return 0;
        }

        $sandi = $this->mintaSandiBaru(true);
        if ($sandi === null) {
            return 1;
        }

        $id =$this->user->buat($email, $nama ?? $email, $sandi);
        $this->user->tambahAkses($id, $this->lingkup);
        printf("Admin %s dibuat untuk lingkup '%s'.%s", DaftarUser::normalkanEmail($email), $this->lingkup, PHP_EOL);

        return 0;
    }

    private function gantiSandi(string $email): int
    {
        $user = $email !== '' ? $this->user->cariEmail($email) : null;
        if ($user === null) {
            return $this->gagal('User tidak ditemukan. Pemakaian: dapur-dashboard ganti-sandi <email>');
        }

        $sandi = $this->mintaSandiBaru($this->user->adalahAdmin($user->id));
        if ($sandi === null) {
            return 1;
        }

        $this->user->gantiSandi($user->id, $sandi);
        printf('Kata sandi %s sudah diganti.%s', $user->email, PHP_EOL);

        return 0;
    }

    private function mintaSandiBaru(bool $admin): ?string
    {
        $minimal = AturanKataSandi::panjangMinimal($admin);
        $sandi   = ($this->bacaSandi)(sprintf('Kata sandi baru (minimal %d karakter): ', $minimal));
        if (strlen($sandi) < $minimal) {
            $this->gagal(sprintf('Kata sandi minimal %d karakter.', $minimal));

            return null;
        }

        if (! hash_equals($sandi, ($this->bacaSandi)('Ulangi kata sandi: '))) {
            $this->gagal('Kedua kata sandi tidak sama.');

            return null;
        }

        return $sandi;
    }

    /** @param list<string> $argumen */
    private function opsi(array $argumen, string $nama): ?string
    {
        foreach ($argumen as $a) {
            if (str_starts_with($a, "--{$nama}=")) {
                return substr($a, strlen($nama) + 3);
            }
        }

        return null;
    }

    private function gagal(string $pesan): int
    {
        fwrite(STDERR, $pesan . PHP_EOL);

        return 1;
    }

    private function bantuan(int $kode): int
    {
        echo <<<BANTUAN
            Pemakaian: dapur-dashboard <perintah>

              pasang                          Membuat tabel dashboard (aman diulang)
              buat-admin <email> [--nama=..]  Membuat admin; kata sandi diketik tanpa terlihat
              ganti-sandi <email>             Mengganti kata sandi user

            Dengan paket adems/dapur-multiwebsite, tambahkan --website=<domain> untuk dashboard website.

            BANTUAN;

        return $kode;
    }
}
