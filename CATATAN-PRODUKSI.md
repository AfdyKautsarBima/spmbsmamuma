# Catatan sebelum dipakai sungguhan

Sistem sudah lengkap sampai tahap 7. Sebelum dipakai untuk data pendaftar asli, mohon periksa daftar ini.

## 1. Keamanan dan pengaturan
- Ganti password akun `admin` (menu nama di pojok kanan atas). Jangan biarkan `ganti-password-ini`.
- Di `.env` server: `APP_ENV=production`, `APP_DEBUG=false`. Jangan pernah `APP_DEBUG=true` di server yang diakses orang lain.
- Pakai database dengan **user khusus aplikasi** (bukan root). Untuk tabel `activity_logs`, beri hak INSERT dan SELECT saja.
- Akses lewat **HTTPS** bila sistem dibuka lewat jaringan. Data NIK dan keuangan tidak boleh lewat HTTP biasa.
- Setelah deploy: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
- Isi nama dan alamat sekolah di `config/sekolah.php`, lalu `php artisan config:clear`.

## 2. Backup (paling penting)
Sistem ini menyimpan data uang dan identitas. Buat backup database terjadwal, misalnya harian:

```bash
mysqldump --single-transaction -u spmb -p spmb | gzip > ~/backup/spmb-$(date +%F).sql.gz
```

Simpan salinannya di perangkat lain (flash disk atau komputer lain), dan **coba pulihkan** sekali untuk memastikan backup bisa dipakai.
Jika memakai MariaDB XAMPP, ganti `mysqldump` dengan `/opt/lampp/bin/mysqldump`.

## 3. Persiapan awal tahun ajaran
1. Menu Tahun Ajaran: buat tahun baru, isi biaya daftar ulang dan batas seragam, lalu aktifkan.
2. Master Data > Diskon: buat periode diskon.
3. Master Data > Jenis Seragam: salin dari tahun lalu atau buat baru.
4. Master Data: pastikan asal sekolah dan pekerjaan sudah lengkap.
5. Menu Akun: buat akun panitia sesuai peran (satu akun satu peran).

## 4. Yang sengaja tidak ada
Halaman publik untuk pendaftar, pengunggahan berkas, pengembalian dana, stok seragam, nominal reward, dan ekspor Dapodik.
Semuanya bisa ditambahkan nanti tanpa merusak data yang ada.
