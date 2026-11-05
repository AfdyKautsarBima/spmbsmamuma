# SPMB SMA - Tahap 2: Master Data

Isi: asal sekolah, pembawa, pekerjaan, wilayah nasional (impor), dan komponen dropdown alamat berjenjang.

## Pemasangan

Salin isi paket ini ke proyek `spmb` (tahap 1 harus sudah terpasang). File yang **menimpa** milik tahap 1:
`routes/web.php`, `resources/views/layouts/app.blade.php`, `database/seeders/DatabaseSeeder.php`.
Semua file lain baru.

```bash
php artisan config:clear
php artisan migrate
php artisan db:seed        # aman diulang: menambah daftar pekerjaan tanpa menggandakan admin/tahun ajaran
php artisan test
```

## Mengimpor data wilayah

Sumber yang dipakai: repositori `cahyadsn/wilayah`, berkas `db/wilayah.sql`
(sesuai Kepmendagri No 300.2.2-2430 Tahun 2025 menurut README repositori tersebut).

```bash
cd ~
git clone https://github.com/cahyadsn/wilayah.git
cd ~/proyek/spmb
php artisan wilayah:import ~/wilayah/db/wilayah.sql
```

Perintah menampilkan jumlah wilayah per tingkat. Hasil yang wajar: sekitar 38 provinsi, 500-an kabupaten/kota,
7 ribuan kecamatan, dan 80 ribuan desa/kelurahan. Jika angkanya jauh berbeda, atau muncul peringatan
"wilayah tidak punya induk", file kemungkinan tidak lengkap.

Jika muncul "Tidak ada baris wilayah yang terbaca", format dump mungkin berbeda dari perkiraan.
Kirimkan hasil `head -c 1500 ~/wilayah/db/wilayah.sql` agar parsernya disesuaikan.

File CSV apa pun juga bisa dipakai: kolom pertama kode, kolom terakhir nama. Kode boleh bertitik
(33.19.01) atau tanpa titik (331901). Ulangi impor kapan saja untuk memperbarui. Tambahkan `--replace`
bila ingin mengosongkan tabel wilayah lebih dulu.

Setelah impor, buka menu Master Data > Wilayah. Halaman itu menampilkan jumlah per tingkat, nama untuk kode
bawaan di `config/spmb.php` (provinsi 33 dan kabupaten 33.20 serta 33.19), dan pemilih alamat untuk dicoba.
**Periksa bahwa nama yang tampil memang Jawa Tengah, Jepara, dan Kudus.** Jika berbeda, sesuaikan kode di config.

## Yang ditambahkan

- **Wilayah:** satu tabel `regions` (4 tingkat). Dropdown memuat per tingkat lewat `/wilayah`.
  Pada pilihan kabupaten, Jepara lalu Kudus tampil paling atas. Provinsi bawaan Jawa Tengah.
- **Komponen `<x-region-picker>`:** dipakai form sekolah (2 tingkat) dan nanti form pendaftar (4 tingkat).
- **Asal sekolah:** nama, NPSN (opsional, 8 karakter huruf/angka, unik), kabupaten/kota asal. Dinonaktifkan, tidak dihapus.
  Nama kabupaten disalin sebagai teks agar tidak berubah bila data wilayah diperbarui.
- **Pembawa:** nama yang sama setelah dinormalkan (abaikan huruf besar/kecil, spasi, titik, gelar seperti
  H., Drs., Pak, Bu) ditolak. Nama mirip (satu nama termuat di nama lain, atau selisih ketikan kecil)
  memunculkan peringatan dan bisa disimpan dengan konfirmasi. Endpoint pencarian dan tambah-langsung
  sudah siap untuk form pendaftaran.
- **Pekerjaan:** daftar awal kategori pekerjaan lazim di KK plus "Lainnya", bisa diubah admin.
- Semua perubahan master tercatat di log aktivitas.

## Belum ada di tahap ini

- Penggabungan nama pembawa ganda (membutuhkan tabel pendaftar, tahap 3).
- Kolom pembawa dan sekolah pada form pendaftar (tahap 3).
