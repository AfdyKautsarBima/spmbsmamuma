<?php

return [
    // Kode wilayah (format Kemendagri bertitik). Provinsi yang terpilih otomatis di form alamat.
    'default_province_code' => env('SPMB_DEFAULT_PROVINCE', '33'), // Jawa Tengah

    // Kabupaten/kota yang ditaruh paling atas pada pilihan kabupaten (berurutan).
    'priority_regency_codes' => ['33.20', '33.19'], // Jepara, Kudus
];
