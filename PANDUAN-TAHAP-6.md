# SPMB SMA - Tahap 6: Seragam, plus format tanggal dd/mm/yyyy

## Cara menyalin ke proyek

Asumsi: zip ada di folder Downloads dan proyek di ~/proyek/spmb.
Tahap 5 harus sudah terpasang.

```bash
cd ~/proyek/spmb
git add -A && git commit -m "sebelum tahap 6"

unzip -o ~/Downloads/spmb-tahap6.zip -d /tmp
cp -r /tmp/spmb-tahap6/* ~/proyek/spmb/

php artisan migrate
php artisan db:seed          # menambah ukuran S sampai XXXL (aman diulang)
php artisan optimize:clear
php artisan test
```

File milik tahap sebelumnya yang **tertimpa** (karena format tanggal dan integrasi seragam):

- `app/Http/Controllers/AcademicYearController.php`
- `app/Http/Controllers/ActivityLogController.php`
- `app/Http/Controllers/ApplicantController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/DiscountPeriodController.php`
- `app/Http/Controllers/EnrollmentController.php`
- `app/Http/Requests/ApplicantRequest.php`
- `app/Models/Applicant.php`
- `app/Services/EnrollmentService.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/activity-logs/index.blade.php`
- `resources/views/applicants/form.blade.php`
- `resources/views/applicants/index.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/discount-periods/form.blade.php`
- `resources/views/enrollment/show.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/partials/master-tabs.blade.php`
- `routes/web.php`

Tidak ada paket Composer atau npm baru.

## Format tanggal

Semua isian tanggal kini berupa kolom teks **dd/mm/yyyy** (mengetik 25122010 otomatis menjadi 25/12/2010),
bukan pemilih tanggal bawaan browser (yang mengikuti bahasa sistem dan bisa tampil bulan/hari/tahun).
Tanggal yang ditampilkan memakai dd/mm/yyyy, dan tanggal pada cetakan memakai hari, nama bulan, tahun
(contoh: 05 Desember 2026). Isian: tanggal lahir, tanggal mundur, periode diskon, dan filter tanggal
(daftar pendaftar dan log aktivitas). Di database tanggal tetap disimpan sebagai yyyy-mm-dd.

## Aturan seragam

- Admin mengatur **jenis seragam per tahun ajaran** (menu Master Data > Jenis Seragam). Tandai "perlu ukuran"
  hanya untuk seragam yang ukurannya dicatat (olahraga). Jenis seragam bisa disalin dari tahun ajaran lain.
  Daftar ukuran (S, M, L, ...) dikelola di Master Data > Ukuran Seragam.
- **Hak:** murid berhak bila total pembayaran sah mencapai nominal batas seragam yang diatur admin di menu Tahun Ajaran.
  Periode diskon dapat menetapkan batas nominal khusus yang dibekukan saat pembayaran pertama. Sebelum pembayaran pertama belum ada hak.
- **Hak bersifat tetap:** ambang yang berlaku saat hak muncul disimpan. Menaikkan batas tidak mencabut hak.
  Menurunkan batas memberi hak kepada murid yang pembayarannya sudah cukup.
- **Pembayaran dibatalkan:** hak dicabut bila seragam belum diambil sama sekali. Bila sudah ada yang diambil, hak tetap
  dan muncul peringatan.
- **Murid mundur** tidak diberi hak baru dan tidak bisa menerima seragam. Bila seragam sudah diambil, muncul peringatan.
- **Ukuran** baru bisa dicatat setelah berhak, bisa diubah selama seragam itu belum diambil (setelah diambil hanya admin).
  Seragam yang perlu ukuran baru bisa diserahkan setelah ukurannya dicatat.
- **Penyerahan:** panitia seragam mencentang per jenis (tercatat tanggal, jam, penyerah). Pembatalan oleh panitia seragam
  hanya pada hari penyerahan. Setelahnya hanya admin dengan alasan wajib.
- **Panitia seragam** melihat nama, nomor, asal sekolah, nominal pembayaran, hak, dan ukuran. Alamat, NIK, nomor HP,
  dan data orang tua tidak ditampilkan.
- Pendaftar yang sudah menerima seragam tidak bisa dihapus.
- Perintah `php artisan seragam:hitung-ulang` memberi hak kepada murid yang sudah cukup bayar (tanpa mencabut hak).

## Belum ada di tahap ini

Rekap reward pembawa, laporan (daftar belum ambil seragam, rekap penyerahan, kebutuhan ukuran per hari,
laporan keuangan, daftar tunggakan, berkas kurang), dan ekspor Excel/PDF: tahap 7.
