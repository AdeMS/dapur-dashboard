# Dapur AdeMS Dashboard

Modul dashboard untuk [Dapur AdeMS](https://github.com/AdeMS/dapur): login dan halaman pengelolaan. Dashboard
bisa dipakai sebagai dashboard utama di inti Dapur, atau sebagai dashboard milik satu website.

## Kebutuhan

- Dapur AdeMS 2.6 atau lebih baru (layout dengan tempat menu di navbar)
- MariaDB atau MySQL yang sudah diatur di `config/autoload/database.local.php` (SQLite juga bisa dipakai)
- [adems/dapur-multiwebsite](https://github.com/AdeMS/dapur-multiwebsite) 1.1 atau lebih baru, untuk halaman
  daftar website dan dashboard per website

## Pemasangan

Tulis paketnya di `composer.local.json`, lalu jalankan `composer update`:

```json
{
    "require": {
        "adems/dapur-dashboard": "^1.0"
    }
}
```

Buat tabel dan admin pertama dari folder inti. Kata sandi diketik tanpa terlihat; minimal 12 karakter untuk
admin dan 8 karakter untuk user lain:

```bash
vendor/bin/dapur-dashboard pasang
vendor/bin/dapur-dashboard buat-admin admin@contoh.com --nama="Nama Lengkap"
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
vendor/bin/dapur-dashboard buat-admin admin@contoh.com --website=contoh.com
```

Akses diberikan per lingkup: admin dashboard utama tidak otomatis bisa masuk ke dashboard website, dan
sebaliknya. Website yang tidak mengaktifkan modul ini tidak memiliki alamat dashboard.

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

## Perintah

```bash
vendor/bin/dapur-dashboard pasang               # membuat tabel, aman diulang
vendor/bin/dapur-dashboard buat-admin <email>   # membuat admin, atau menambah akses user yang sudah ada
vendor/bin/dapur-dashboard ganti-sandi <email>  # mengganti kata sandi
```

Session disimpan di `data/cache/session/`, jadi folder `data/cache/` harus bisa ditulis web server.

## Keamanan

- Kata sandi disimpan sebagai hash bcrypt.
- Cookie session memakai HttpOnly dan SameSite=Lax, ditambah Secure pada HTTPS. ID session diganti saat login.
- Semua form memakai token CSRF. Keluar hanya bisa lewat POST.
- Login gagal dibatasi per akun dan per IP selama 15 menit.
- Status dan hak akses user diperiksa ulang pada setiap request.

## Uji

```bash
composer test
```

## Lisensi

BSD-3-Clause. Lihat [LICENSE.md](LICENSE.md).
