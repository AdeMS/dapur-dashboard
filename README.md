# Dapur AdeMS Dashboard

Modul dashboard untuk [Dapur AdeMS](https://github.com/AdeMS/dapur): halaman pengelolaan, dengan login dari
[adems/dapur-user](https://github.com/AdeMS/dapur-user). Dashboard bisa dipakai sebagai dashboard utama di inti
Dapur, atau sebagai dashboard milik satu website.

## Kebutuhan

- Dapur AdeMS 2.6 atau lebih baru (layout dengan tempat menu di navbar)
- [adems/dapur-user](https://github.com/AdeMS/dapur-user) 1.1 (ikut terpasang otomatis)
- MariaDB atau MySQL yang sudah diatur di `config/autoload/database.local.php` (SQLite juga bisa dipakai)
- [adems/dapur-multiwebsite](https://github.com/AdeMS/dapur-multiwebsite) 1.1 atau lebih baru, untuk halaman
  daftar website dan dashboard per website

## Pemasangan

Tulis paketnya di `composer.local.json`, lalu jalankan `composer update`:

```json
{
    "require": {
        "adems/dapur-dashboard": "^2.1"
    }
}
```

Bila tabel user perlu awalan nama, atur `'user' => ['awalan_tabel' => ...]` lebih dulu (lihat
[README dapur-user](https://github.com/AdeMS/dapur-user#pengaturan)). Lalu buat tabel dan admin pertama dari
folder inti. Kata sandi diketik tanpa terlihat; minimal 12 karakter untuk admin:

```bash
vendor/bin/dapur-user pasang
vendor/bin/dapur-user buat-admin admin@contoh.com --nama="Nama Lengkap"
```

Lalu aktifkan salah satu lapis di bawah, dan hapus cache konfigurasi.

## Dua lapis dashboard

### Dashboard utama

Berada di alamat `/`, dengan tambahan halaman yang menyangkut semua website. Aktifkan di
`config/modul.local.php` milik inti:

```php
return [
    AdeMS\Dapur\Dashboard\DashboardUtamaConfigProvider::class,
];
```

Dengan multi-website aktif, dashboard utama hanya tampil pada host yang tercantum di `'dashboard'` pada
`config/multiwebsite.local.php`. Domain website tidak memiliki halaman ini.

### Dashboard website

Berada di alamat `/dashboard`, sehingga halaman `/` tetap milik website. Aktifkan di `config/website.php` milik
website tersebut:

```php
return [
    'judul' => 'Contoh',
    'modul' => [AdeMS\Dapur\Dashboard\ConfigProvider::class],
];
```

Admin dashboard website dibuat dengan opsi `--website`:

```bash
vendor/bin/dapur-user buat-admin admin@contoh.com --website=contoh.com
```

Akses diberikan per lingkup: admin dashboard utama tidak otomatis bisa masuk ke dashboard website, dan
sebaliknya. Website yang tidak mengaktifkan modul ini tidak memiliki alamat dashboard.

### Profil

Setiap dashboard punya halaman profil dari paket user, di `/profil` (dashboard utama) atau `/dashboard/profil`
(dashboard website), dengan tautan di submenu avatar. Di sana user mengubah nama, mengganti kata sandi, dan
melihat riwayat loginnya. Halaman ini tampil dengan menu dashboard; isinya dijelaskan di
[README dapur-user](https://github.com/AdeMS/dapur-user#profil).

## Pengaturan

Tulis di `config/autoload/*.php`, milik inti atau milik website:

```php
return [
    'dashboard' => [
        'alamat_dasar' => '/admin',          // alamat dashboard
        'batas_diam'   => 3600,              // detik tanpa aktivitas sebelum dikeluarkan
        'layout'       => 'layout::default', // template tata letak
        'menu'         => [                  // ditambahkan ke menu bawaan
            ['label' => 'Laporan', 'rute' => 'laporan', 'ikon' => 'bar-chart'],
        ],
    ],
    'templates' => [
        'paths' => [
            'dashboard' => [__DIR__ . '/../../templates/dashboard'], // menimpa template bawaan
        ],
    ],
];
```

Website bisa memakai database sendiri lewat `config/autoload/*.local.php` miliknya. Untuk dashboard yang sama
sekali berbeda, buat modul sendiri di folder `module/` website tanpa paket ini.

## Memperbarui dari 2.0

Ubah `composer.local.json` menjadi `"adems/dapur-dashboard": "^2.1"`, jalankan `composer update`, lalu hapus cache
konfigurasi. Tidak ada perubahan tabel. Bila template `ringkasan` atau `website` ditimpa sendiri, tambahkan
`'alamatProfil' => $this->alamatProfil` pada pemanggilan `dashboard::menu` agar tautan Profil muncul.

## Memperbarui dari 1.x

Login, user, dan perintahnya pindah ke paket [adems/dapur-user](https://github.com/AdeMS/dapur-user), yang
ikut terpasang otomatis.

1. Ubah `composer.local.json` menjadi `"adems/dapur-dashboard": "^2.1"`, lalu jalankan `composer update`.
2. Versi 1.x selalu memakai tabel `adems_user`, `adems_user_akses`, dan `adems_percobaan_masuk`. Untuk tetap
   memakai tabel itu, atur awalan `adems_` di `config/autoload/*.php` milik inti:

   ```php
   return ['user' => ['awalan_tabel' => 'adems_']];
   ```

3. Ganti nama satu tabel yang nama dasarnya berubah:

   ```sql
   RENAME TABLE adems_percobaan_masuk TO adems_user_percobaan_masuk;
   ```

   Tabel itu hanya berisi catatan login gagal 15 menit terakhir, jadi boleh juga dihapus.
4. Jalankan `vendor/bin/dapur-user pasang` untuk membuat tabel yang belum ada, termasuk tabel riwayat login
   `adems_user_riwayat_masuk`.
5. Hapus cache konfigurasi.

Perubahan lain yang perlu diperhatikan:

- Perintah `vendor/bin/dapur-dashboard` diganti `vendor/bin/dapur-user`, dengan perintah yang sama ditambah
  `riwayat`.
- Semua user perlu login ulang satu kali, karena kunci session berubah.
- Template halaman login pindah dari `dashboard::masuk` ke `user::masuk`, dan menu user ke `user::menu-user`.
  Template yang ditimpa sendiri perlu dipindahkan ke `'templates' => ['paths' => ['user' => [...]]]`. Kelas CSS
  `dashboard-avatar` dan `dashboard-masuk-*` menjadi `user-avatar` dan `user-masuk-*`.

## Keamanan

Login, session, token CSRF, dan pembatasan percobaan diatur oleh paket dapur-user; lihat
[README dapur-user](https://github.com/AdeMS/dapur-user#keamanan). Halaman dashboard selalu memeriksa ulang
status user dan hak aksesnya ke lingkup dashboard pada setiap request.

## Uji

```bash
composer test
```

## Lisensi

BSD-3-Clause. Lihat [LICENSE.md](LICENSE.md).
