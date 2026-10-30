# SPMB SMA - Tahap 5: Daftar Ulang dan Pembayaran

## Cara menyalin ke proyek

Asumsi: zip ada di folder Downloads dan proyek di ~/proyek/spmb.
Tahap 4 beserta pembaruannya (`spmb-tahap4-pembaruan.zip`) harus sudah terpasang.

```bash
cd ~/proyek/spmb
git add -A && git commit -m "sebelum tahap 5"

unzip -o ~/Downloads/spmb-tahap5.zip -d /tmp
cp -r /tmp/spmb-tahap5/* ~/proyek/spmb/

php artisan migrate
php artisan optimize:clear
php artisan test
```

File milik tahap sebelumnya yang **tertimpa**:
`routes/web.php`, `resources/views/layouts/app.blade.php`, `resources/views/dashboard.blade.php`,
`resources/views/applicants/show.blade.php`, `resources/views/partials/master-tabs.blade.php`,
`app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/ApplicantController.php`,
`app/Models/Applicant.php`. File lain baru. Tidak ada paket Composer atau npm baru.

Sebelum mencoba pembayaran, **isi biaya daftar ulang** di menu Tahun Ajaran (bawaan 0, pembayaran ditolak
selama biaya belum diisi). Buat akun dengan peran "Panitia Daftar Ulang" di menu Akun.

## Ringkasan aturan

- Status dihitung otomatis, tidak disimpan manual: Terdaftar (belum ada pembayaran sah), Daftar ulang menyicil,
  Daftar ulang lunas (total sah >= tagihan bersih), Mengundurkan diri.
- **Tagihan dibekukan** saat pembayaran pertama (biaya dan diskon periode). Perubahan biaya atau periode sesudahnya
  tidak memengaruhi murid itu.
- **Diskon periode** mengikuti tanggal pembayaran pertama. Persen 1 sampai 99, tagihan bersih dibulatkan ke bawah.
  Periode aktif tidak boleh tumpang tindih. Periode tidak dihapus, hanya dinonaktifkan.
- **Diskon manual** (admin, wajib alasan, 0 sampai 99%) menggantikan diskon periode. Persen 0 = tanpa diskon.
- **Pembayaran** tidak bisa diubah atau dihapus. Admin membatalkannya dengan alasan, barisnya tetap terlihat.
  Nominal tidak boleh melebihi sisa tagihan. Nomor kwitansi `KW-2026-00001` unik per tahun ajaran dan
  tidak dipakai ulang walau pembayarannya dibatalkan.
- **Pembayaran pertama dibatalkan** dan tidak ada pembayaran sah lain: murid kembali Terdaftar dan tanda diskon periode
  dilepas (diskon manual tetap).
- **Mundur:** tanpa pembayaran, panitia daftar ulang boleh. Sudah membayar: hanya admin. Pembayaran tidak bisa
  ditambah setelah mundur. Riwayat pembayaran tidak berubah. Pengembalian dana hanya dicatat (admin).
- **Pembawa terkunci** sejak pembayaran pertama sah (hanya admin yang boleh mengubah). Terlepas bila pembayarannya dibatalkan.
- **Pendaftar yang punya pembayaran sah tidak bisa dihapus.**
- Panitia pendaftaran melihat status dan nominal pembayaran (hanya baca) di halaman detail pendaftar.
- Semua perubahan tercatat di log aktivitas dan tab riwayat pendaftar.

## Belum ada di tahap ini

- Hak seragam dan peran panitia seragam: tahap 6.
- Rekap reward pembawa, laporan harian per panitia, daftar tunggakan, ekspor Excel/PDF: tahap 7.
