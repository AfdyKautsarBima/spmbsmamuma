# SPMB SMA - Tahap 1: Fondasi

Isi: login username, 4 peran, tahun ajaran, kelola akun, ganti password, log aktivitas.

## Pemasangan

```bash
composer create-project laravel/laravel spmb
cd spmb
```

Salin isi paket ini ke dalam folder `spmb`, timpa file yang sama:
`bootstrap/app.php`, `routes/web.php`, `app/`, `database/`, `resources/views/`, `tests/`.

Atur lokal dan zona waktu:

```bash
# .env
APP_NAME="SPMB SMA"
APP_LOCALE=id
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta
SEED_ADMIN_PASSWORD=isi-password-awal-yang-kuat

# config/app.php
sed -i "s/'timezone' => 'UTC'/'timezone' => env('APP_TIMEZONE', 'Asia\/Jakarta')/" config/app.php
```

Terjemahan pesan validasi bahasa Indonesia:

```bash
composer require laravel-lang/common
php artisan lang:add id
```

Database: bawaan Laravel adalah SQLite (cukup untuk mencoba). Untuk produksi, atur
`DB_CONNECTION=mysql` (atau mariadb) beserta host, nama database, user, dan password di `.env`.

Jalankan:

```bash
php artisan migrate --seed
npm install && npm run build
php artisan test
php artisan serve        # lalu buka http://localhost:8000
```

Login awal: username `admin`, password sesuai `SEED_ADMIN_PASSWORD`
(bawaan `ganti-password-ini` bila tidak diisi). Segera ganti lewat menu nama di pojok kanan atas.

## Catatan

- Tidak ada registrasi dan lupa password. Admin membuat akun dan mereset password.
- Login dikunci 5 menit setelah 5 kali gagal (per username + IP).
- Akun yang dinonaktifkan langsung keluar dari semua sesi.
- Log aktivitas dijaga di level aplikasi (tidak bisa diubah/dihapus lewat Eloquent). Untuk produksi,
  sebaiknya user database aplikasi hanya diberi hak INSERT dan SELECT pada tabel `activity_logs`.
- Satu tahun ajaran aktif pada satu waktu. Tahun ajaran tidak bisa dihapus.
