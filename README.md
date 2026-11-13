# Dapur AdeMS Dashboard

Modul dashboard untuk [Dapur AdeMS](https://github.com/AdeMS/dapur): halaman pengelolaan, dengan login dari
[adems/dapur-user](https://github.com/AdeMS/dapur-user). Dashboard bisa dipakai sebagai dashboard utama di inti
Dapur, atau sebagai dashboard milik satu website.

## Kebutuhan

- Dapur AdeMS 2.9 atau lebih baru (layout dengan menu samping dan placeholder `menu-alat`, log error)
- [adems/dapur-user](https://github.com/AdeMS/dapur-user) 2.0 (ikut terpasang otomatis)
- MariaDB atau MySQL yang sudah diatur di `config/autoload/database.local.php` (SQLite juga bisa dipakai)
- [adems/dapur-multiwebsite](https://github.com/AdeMS/dapur-multiwebsite) 1.1 atau lebih baru, untuk halaman
  daftar website dan dashboard per website; 1.2 agar dashboard utama membaca log error semua website

## Pemasangan

Tulis paketnya di `composer.local.json`, lalu jalankan `composer update`:

```json
{
    "require": {
        "adems/dapur-dashboard": "^2.3"
    }
}
```

Bila tabel user perlu awalan nama, atur `'user' => ['awalan_tabel' => ...]` lebih dulu (lihat
[README dapur-user](https://github.com/AdeMS/dapur-user#pengaturan)). Lalu buat tabel dan admin pertama dari
folder inti. Admin yang dibuat di inti menjadi admin super. Kata sandi diketik tanpa terlihat; minimal 12
karakter untuk admin:

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

### Menu samping

Semua halaman dashboard memakai menu samping di kiri, berisi item dari pengaturan `'menu'`. Di layar lebar menu
selalu tampil dan tetap terlihat saat halaman digulir. Di layar kecil menu disembunyikan dan dibuka dengan tombol
menu (hamburger) di kiri navbar sebagai panel geser. Menu user (avatar) tetap di navbar. Di layar lebar, navbar
juga menampilkan tombol layar penuh di kanan pemilih tema.

Template `dashboard::kerangka` mengisi placeholder `menu-samping`, `menu-alat` (layar penuh dan lonceng), dan
`menu-kanan` (avatar) milik layout Dapur 2.9; kelas
`Tampilan` merender halaman lebih dulu, lalu memasukkan hasilnya ke kerangka sebagai `$isi`. Template halaman
cukup berisi isinya sendiri. Dengan layout sendiri yang tidak menampilkan `menu-samping`, menu dashboard tidak
terlihat.

### Kelola user

Menu **User** di dashboard, `/user` (dashboard utama) atau `/dashboard/user` (dashboard website), hanya tampil
untuk admin super dan admin web. Di sana admin menambah user, mengubah peran (admin super atau anggota di
dashboard utama; admin web atau anggota di website), mencabut akses, dan melihat aktivitas terakhir. Admin super
otomatis menjadi admin di dashboard semua website, tanpa perlu diberi akses satu per satu. Mengaktifkan dan menonaktifkan akun hanya ada di dashboard utama, karena akun berlaku di semua
website. Rinciannya ada di [README dapur-user](https://github.com/AdeMS/dapur-user#kelola-user).

Item menu bisa dibatasi untuk peran tertentu dengan kunci `'peran'`. Nilai `'admin'` berarti admin mana pun
(admin super atau admin web); nilai lain, misalnya `'admin_super'` atau `'anggota'`, harus sama persis:

```php
'dashboard' => [
    'menu' => [
        ['label' => 'Laporan', 'rute' => 'laporan', 'ikon' => 'graph-up', 'peran' => 'admin'],
    ],
],
```

### Pesan Error

Admin super dan admin web melihat ikon lonceng di navbar, di antara tombol layar penuh dan avatar. Angkanya menunjukkan jumlah error yang
belum dibaca oleh admin itu (lebih dari 99 ditampilkan "99+"). Dropdown-nya menampilkan 5 error terakhir, dengan
label **baru** untuk yang belum dibaca, dan tautan **Lihat semua**.

Halaman Pesan Error berada di `/error` (dashboard utama) atau `/dashboard/error` (dashboard website):

- di dashboard utama, berisi log error inti dan **semua** website (dengan adems/dapur-multiwebsite 1.2);
- di dashboard website, berisi log error website itu saja.

Halaman ini menampilkan 50 error terbaru; tombol **Tampilkan lebih banyak** menambah 50 berikutnya. Setiap error
bisa dibuka untuk melihat penyebab dan jejak pemanggilannya, dan kode kesalahan dari halaman error bisa dicari.

Tanda "sudah dibaca" berlaku per error, seperti kotak masuk email: membuka satu error dari dropdown atau membuka
detailnya di halaman ini menandai error itu saja, dan tombol **Tandai semua sudah dibaca** menandai semuanya. Tanda
ini disimpan per admin dan per dashboard di tabel `user_pengaturan` dari adems/dapur-user 2.0, sebagai batas waktu
ditambah daftar kode yang dibaca sesudahnya (paling banyak sekitar 25 kode; bila lebih, yang tertua tampil baru
lagi).

Membaca tidak menghapus error. Error dihapus otomatis oleh log error inti setelah 90 hari (bisa diatur dengan
`'log' => ['error_hari' => ...]`) atau bila file log melewati batas ukurannya. Sumber datanya adalah log error
Dapur AdeMS 2.9 atau lebih baru (lihat [Log Error](https://github.com/AdeMS/dapur#log-error)); pada inti yang lebih
lama, lonceng tidak tampil.

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

## Memperbarui dari 2.2

Dashboard 2.3 memakai adems/dapur-user 2.0, yang mengganti peran `admin` dengan admin super dan admin web. Ubah
`composer.local.json` menjadi `"adems/dapur-dashboard": "^2.3"`, lalu jalankan dari folder inti:

```bash
composer update
vendor/bin/dapur-user pasang
```

lalu hapus cache konfigurasi. `pasang` membuat tabel `user_pengaturan` dan mengubah peran lama: admin di dashboard
utama menjadi admin super, admin di website menjadi admin web. Perbarui juga inti ke Dapur AdeMS 2.9: tombol layar penuh dan lonceng
memakai placeholder `menu-alat` dari layout 2.9, dan lonceng memakai log error 2.9. Pengaturan menu `'peran' => 'admin'` tetap berlaku untuk admin super dan admin
web.

## Memperbarui dari 2.1

Ikuti langkah dari 2.2 di atas, lalu langkah di bawah.

Dari 2.1 ke 2.2, ubah `composer.local.json` menjadi `"adems/dapur-dashboard": "^2.2"`, lalu jalankan dari folder inti:

```bash
composer update
vendor/bin/dapur-user pasang
```

lalu hapus cache konfigurasi. `pasang` membuat tabel riwayat akun dari adems/dapur-user 1.2; sebelum itu halaman
User akan gagal.

Dashboard 2.2 membutuhkan Dapur AdeMS 2.7, karena menu dashboard pindah dari navbar ke menu samping milik layout
Dapur 2.7. Template `dashboard::menu` diganti `dashboard::kerangka`, yang membungkus setiap halaman secara otomatis. Bila template halaman dashboard ditimpa sendiri, hapus pemanggilan
`$this->partial('dashboard::menu', ...)` di dalamnya; bila tidak, template akan gagal karena `dashboard::menu`
sudah tidak ada.

## Memperbarui dari 2.0

Ikuti langkah dari 2.2 dan 2.1 di atas.

## Memperbarui dari 1.x

Login, user, dan perintahnya pindah ke paket [adems/dapur-user](https://github.com/AdeMS/dapur-user), yang
ikut terpasang otomatis.

1. Ubah `composer.local.json` menjadi `"adems/dapur-dashboard": "^2.3"`, lalu jalankan `composer update`.
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
