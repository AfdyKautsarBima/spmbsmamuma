# Pembaruan: aturan kelengkapan KTP orang tua

## Aturan baru
KTP orang tua dianggap lengkap bila salah satu terpenuhi:
1. KTP wali diterima; atau
2. KTP ayah dan ibu diterima; atau
3. satu orang tua tidak ada/almarhum dan KTP orang tua yang lain diterima, tanpa wali.

Orang tua dianggap "tidak ada/almarhum" bila status KTP-nya **Tidak ada**, atau dicentang
"Tidak ada / almarhum" pada data KK. Bila kedua orang tua tidak ada, KTP wali tetap wajib.

## Cara memasang
```bash
cd ~/proyek/spmb
unzip -o ~/Downloads/spmb-tahap4-pembaruan.zip -d /tmp
cp -r /tmp/spmb-tahap4-pembaruan/* ~/proyek/spmb/
php artisan optimize:clear
php artisan berkas:hitung-ulang     # WAJIB sekali: memperbarui status pendaftar yang sudah tersimpan
php artisan test
```
