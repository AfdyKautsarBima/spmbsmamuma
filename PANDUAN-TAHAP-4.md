# SPMB SMA - Tahap 4: Cek Berkas, Data KK, Wali, NISN

## Cara menyalin ke proyek

Asumsi: zip ada di folder Downloads dan proyek di ~/proyek/spmb.

```bash
cd ~/proyek/spmb

# (disarankan) simpan kondisi sekarang
git add -A && git commit -m "sebelum tahap 4"

unzip -o ~/Downloads/spmb-tahap4.zip -d /tmp
cp -r /tmp/spmb-tahap4/* ~/proyek/spmb/

php artisan migrate
php artisan optimize:clear
php artisan test
```

File milik tahap sebelumnya yang **tertimpa**:

- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/applicants/index.blade.php`
- `resources/views/applicants/show.blade.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Models/Applicant.php`

File lain baru. Migrasi menambah kolom `nisn` dan `documents_complete` pada tabel `applicants` serta
dua tabel baru (`applicant_documents`, `applicant_family_details`). Data pendaftar yang sudah ada tidak berubah.
Tidak ada paket Composer atau npm baru.

## Yang ditambahkan

- **Menu Cek Berkas** (admin dan panitia pendaftaran): daftar pendaftar tahun aktif dengan status
  Lengkap/Belum lengkap, apa yang masih kurang, filter, dan pencarian (nama, nomor, NISN).
- **Layar cek berkas per pendaftar:** 11 baris centang. Dari 9 jenis berkas yang Anda sebut,
  KTP dipecah menjadi KTP ayah, ibu, dan wali sesuai aturan kelengkapan kita.
  - Berkas wajib (akta, KK, FC SKL, foto): Belum atau Diterima.
  - KTP ayah/ibu/wali dan berkas kondisional (SKTM, KKS, PKH, sertifikat): Belum, Diterima, atau Tidak ada.
  - Setiap perubahan status mencatat waktu dan panitia yang mengubah.
  - Sertifikat kejuaraan punya kolom keterangan.
- **Lengkap bila:** akta, KK, FC SKL, foto Diterima; (KTP ayah dan ibu Diterima) atau KTP wali Diterima;
  SKTM, KKS, PKH, sertifikat sudah diputuskan (Diterima atau Tidak ada).
- **KK diterima** membuka form data KK (tidak bisa disimpan sebelum lengkap): nomor KK, NIK murid,
  nama ayah dan ibu (terisi dari data pendaftaran, boleh dikoreksi), NIK, tahun lahir, dan pekerjaan
  orang tua. Koreksi nama tercatat di riwayat dan memperbarui data pendaftar.
- **Orang tua tidak ada / almarhum:** kotak centang per orang tua menonaktifkan NIK, tahun lahir, dan pekerjaan.
  Form **wali** (nama, NIK, hubungan, pekerjaan, HP opsional) muncul bila salah satu orang tua tidak ada:
  wajib bila kedua orang tua tidak ada, opsional bila hanya satu.
- **FC SKL diterima** membuka isian NISN (wajib, 10 digit, unik per tahun ajaran).
- **Aturan unik:** NIK murid dan NISN unik per tahun ajaran (data yang dihapus tetap dihitung).
  Nomor KK boleh sama (saudara kandung), sistem hanya memberi catatan.
- **Tahun lahir orang tua** dianggap wajar bila minimal 1920 dan sedikitnya 12 tahun lebih tua dari murid.
- Membatalkan centang KK atau SKL **tidak menghapus** data KK atau NISN yang sudah tersimpan.
- Detail pendaftar menampilkan ringkasan berkas, NISN, dan data KK. Dashboard menampilkan jumlah berkas
  lengkap dan belum lengkap. Daftar pendaftar punya kolom status berkas.
- Semua perubahan tercatat di log aktivitas (status berkas, data KK, NISN).

## Belum ada di tahap ini

- Status berkas dibaca panitia daftar ulang sebagai informasi: dibuat di tahap 5.
- Laporan "daftar berkas kurang": tahap 7.
