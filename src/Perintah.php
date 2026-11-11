<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

use Closure;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\Source\Factory;

/** Perintah baris: pasang, buat-admin, ganti-sandi. */
final class Perintah
{
    /**
     * @param Closure(string): string      $bacaSandi meminta kata sandi tanpa menampilkannya
     * @param (Closure(string): string)|null $tanya   meminta jawaban biasa; null bila tidak dijalankan di
     *                                               terminal, sehingga tidak ada yang ditanyakan
     */
    public function __construct(
        private readonly Adapter $db,
        private readonly DaftarUser $user,
        private readonly Closure $bacaSandi,
        private readonly string $lingkup,
        private readonly ?Closure $tanya = null,
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

    /** Membuat tabel yang belum ada. Tabel yang sudah ada tidak diubah, jadi aman diulang. */
    private function pasang(): int
    {
        $ada = Factory::createSourceFromAdapter($this->db)->getTableNames();

        foreach (Skema::tabel($this->db->getPlatform()->getName()) as $tabel => $sql) {
            if (in_array($tabel, $ada, true)) {
                printf("Tabel %-22s sudah ada%s", $tabel, PHP_EOL);
                continue;
            }

            $this->db->query($sql, Adapter::QUERY_MODE_EXECUTE);
            printf("Tabel %-22s dibuat%s", $tabel, PHP_EOL);
        }

        echo PHP_EOL . 'Tabel dashboard sudah siap; data yang sudah ada tidak diubah.' . PHP_EOL;

        return 0;
    }

    private function buatAdmin(string $email, ?string $nama): int
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->gagal('Pemakaian: dapur-dashboard buat-admin <email> [--nama="Nama Lengkap"]');
        }

        $ada = $this->user->cariEmail($email);
        if ($ada !== null) {
            return $this->userSudahAda($ada);
        }

        $sandi = $this->mintaSandiBaru(true);
        if ($sandi === null) {
            return 1;
        }

        $id = $this->user->buat($email, $nama ?? $email, $sandi);
        $this->user->tambahAkses($id, $this->lingkup);
        printf("Admin %s dibuat untuk lingkup '%s'.%s", DaftarUser::normalkanEmail($email), $this->lingkup, PHP_EOL);

        return 0;
    }

    /** Memastikan akses ke lingkup ini, lalu menawarkan ganti kata sandi (bawaan: tidak). */
    private function userSudahAda(User $user): int
    {
        if ($this->user->punyaAkses($user->id, $this->lingkup)) {
            printf("User %s sudah ada dan sudah memiliki akses ke '%s'.%s", $user->email, $this->lingkup, PHP_EOL);
        } else {
            $this->user->tambahAkses($user->id, $this->lingkup);
            printf("User %s sudah ada; akses admin ke '%s' ditambahkan.%s", $user->email, $this->lingkup, PHP_EOL);
        }

        if ($this->tanya === null) {
            echo 'Kata sandi tidak diubah. Gunakan perintah ganti-sandi untuk menggantinya.' . PHP_EOL;

            return 0;
        }

        $jawaban = strtolower(trim(($this->tanya)('Ganti kata sandinya sekarang? [y/N]: ')));
        if (! in_array($jawaban, ['y', 'ya'], true)) {
            echo 'Kata sandi tidak diubah.' . PHP_EOL;

            return 0;
        }

        $sandi = $this->mintaSandiBaru($this->user->adalahAdmin($user->id));
        if ($sandi === null) {
            return 1;
        }

        $this->user->gantiSandi($user->id, $sandi);
        printf('Kata sandi %s sudah diganti.%s', $user->email, PHP_EOL);

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

              pasang                          Membuat tabel yang belum ada (aman diulang)
              buat-admin <email> [--nama=..]  Membuat admin, atau menambah akses user yang sudah ada
              ganti-sandi <email>             Mengganti kata sandi user

            Dengan paket adems/dapur-multiwebsite, tambahkan --website=<domain> untuk dashboard website.

            BANTUAN;

        return $kode;
    }
}
