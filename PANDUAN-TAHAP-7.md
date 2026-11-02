# SPMB SMA - Tahap 7: Laporan, Rekap Reward, dan Ekspor

## Cara menyalin ke proyek

Asumsi: zip ada di folder Downloads dan proyek di ~/proyek/spmb. Tahap 6 harus sudah terpasang.

```bash
cd ~/proyek/spmb
git add -A && git commit -m "sebelum tahap 7"

unzip -o ~/Downloads/spmb-tahap7.zip -d /tmp
cp -r /tmp/spmb-tahap7/* ~/proyek/spmb/

php artisan optimize:clear
php artisan test
```

Tidak ada migrasi dan **tidak ada paket Composer atau npm baru**.
File lama yang tertimpa: `routes/web.php` dan `resources/views/layouts/app.blade.php` (menambah menu Laporan).

## Menu Laporan

Menu "Laporan" ada untuk semua peran. Setiap peran hanya melihat laporan miliknya:

| Peran | Laporan |
|---|---|
| Admin | Semua |
| Panitia pendaftaran | Daftar Pendaftar, Daftar Berkas Kurang |
| Panitia daftar ulang | Daftar Daftar Ulang, Rekap Pembayaran Harian, Daftar Tunggakan, Laporan Keuangan, Rekap Diskon |
| Panitia seragam | Daftar Berhak Seragam Belum Diambil, Rekap Penyerahan Seragam, Rekap Kebutuhan Ukuran Seragam, Murid Mundur dengan Seragam Sudah Diambil |
| Admin saja | Rekap Reward Pembawa (beserta rincian per pembawa) |

Setiap laporan punya tombol:
- **Unduh Excel**: berkas .xlsx asli (angka tetap angka dan bisa dijumlahkan, NIK/NISN tetap teks).
- **Cetak / Simpan PDF**: halaman cetak landscape dengan kop sekolah. Pilih "Simpan sebagai PDF" di dialog cetak browser.

Admin bisa memilih tahun ajaran lain (arsip) di setiap laporan. Peran lain hanya melihat tahun ajaran aktif.

## Aturan reward pembawa

Per pembawa dan per tahun ajaran:
- **Dihitung reward:** murid yang sudah membayar (menyicil atau lunas) dan tidak mengundurkan diri.
- **Belum bayar:** murid yang belum punya pembayaran sah.
- **Mundur (batal):** murid yang mengundurkan diri. Reward-nya batal.
- Reward mengikuti pembayaran yang sah: bila pembayaran pertama dibatalkan, murid pindah ke "Belum bayar".
- Setiap angka bisa diklik untuk melihat daftar muridnya.
- Nominal reward dan status "sudah dibayar" tidak ada di sistem.

## Catatan teknis

- Berkas Excel dibuat sendiri (tanpa paket) dan sudah diperiksa terbuka di LibreOffice dan openpyxl.
- Laporan "Rekap Pembayaran Harian" menghitung uang masuk apa adanya, termasuk dari murid yang kemudian mundur,
  agar cocok dengan kas. Pembayaran yang dibatalkan ditampilkan tetapi tidak dijumlahkan.
- Laporan Keuangan menampilkan kolom "Catatan pengembalian" hanya untuk admin.
