# SPMB SMA - Tahap 3: Pendaftaran

Isi: form input pendaftar, daftar pendaftar, detail dan riwayat, cetak bukti pendaftaran,
penggabungan pembawa ganda.

## Cara menyalin ke proyek

Asumsi: zip ada di folder Downloads dan proyek di ~/proyek/spmb.

```bash
cd ~/proyek/spmb

# (disarankan) simpan kondisi sekarang agar mudah dibandingkan atau dikembalikan
git init 2>/dev/null; git add -A && git commit -m "sebelum tahap 3" 2>/dev/null

# salin isi zip, timpa file yang sama
unzip -o ~/Downloads/spmb-tahap3.zip -d /tmp
cp -r /tmp/spmb-tahap3/* ~/proyek/spmb/

# jalankan migrasi dan bersihkan cache
php artisan migrate
php artisan optimize:clear

# uji
php artisan test
```

File milik tahap sebelumnya yang **tertimpa** oleh paket ini:

- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard.blade.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/ReferrerController.php`
- `app/Models/Referrer.php`
- `resources/views/referrers/index.blade.php`

Jika Anda pernah mengubah file-file itu sendiri, bandingkan dulu dengan `git diff` setelah menyalin.
File lain baru, termasuk `config/sekolah.php` dan migrasi `applicants`.
Tidak ada paket Composer atau npm baru. Tidak perlu `npm run build` ulang kecuali tampilan tidak berubah.

## Mengisi nama sekolah untuk kop bukti pendaftaran

Edit `config/sekolah.php` (nama dan alamat sekolah). Lalu `php artisan config:clear`.

## Yang ditambahkan

- **Form pendaftar:** nomor manual `A-001` dengan petunjuk nomor terakhir/berikutnya, alamat berjenjang sampai
  desa, RT dan RW (disimpan 3 digit), nomor HP (diawali 08), asal sekolah (bisa disaring dengan mengetik),
  pembawa (ketik, pilih, atau tambah baru langsung dari form).
- **Nomor:** unik per tahun ajaran. Nomor milik data yang dihapus tetap terpakai. Peringatan (bukan blokir)
  bila nomor melompat dari nomor terakhir.
- **Data ganda:** peringatan bila nama + tanggal lahir + nama ibu sama dengan pendaftar lain di tahun yang sama.
- **Setelah menyimpan** form kembali kosong agar input berikutnya bisa langsung lanjut, dengan tautan cetak bukti.
- **Daftar pendaftar:** cari nama/nomor/orang tua, filter sekolah dan tanggal input. Admin juga bisa memilih
  tahun ajaran arsip (hanya baca) dan melihat data yang dihapus.
- **Detail:** semua data dan tab riwayat perubahan.
- **Hapus dan pulihkan:** hanya admin, wajib alasan, tidak menghapus permanen.
- **Cetak bukti:** halaman siap cetak. Untuk PDF, pilih "Simpan sebagai PDF" di dialog cetak browser.
- **Penggabungan pembawa:** admin memilih pembawa ganda lalu tujuannya. Semua pendaftar (termasuk yang
  dihapus) dipindah, tiap perpindahan tercatat di riwayat pendaftar.

## Belum ada di tahap ini

- Penguncian pembawa setelah pembayaran pertama (butuh data pembayaran, tahap 5). Kerangkanya sudah ada.
- Penolakan hapus pendaftar yang sudah punya pembayaran (tahap 5).
- Status berkas, daftar ulang, dan seragam (tahap 4 sampai 6).
