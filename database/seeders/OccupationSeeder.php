<?php

namespace Database\Seeders;

use App\Models\Occupation;
use Illuminate\Database\Seeder;

class OccupationSeeder extends Seeder
{
    /** Kategori pekerjaan yang lazim pada KK. Admin bisa menambah, mengubah, atau menonaktifkan. */
    private const NAMES = [
        'Belum/Tidak Bekerja', 'Mengurus Rumah Tangga', 'Pelajar/Mahasiswa', 'Pensiunan',
        'Pegawai Negeri Sipil', 'Tentara Nasional Indonesia', 'Kepolisian RI',
        'Karyawan Swasta', 'Karyawan BUMN', 'Karyawan BUMD', 'Karyawan Honorer',
        'Wiraswasta', 'Perdagangan', 'Petani/Pekebun', 'Peternak', 'Nelayan/Perikanan',
        'Industri', 'Konstruksi', 'Transportasi', 'Sopir',
        'Buruh Harian Lepas', 'Buruh Tani/Perkebunan', 'Buruh Nelayan/Perikanan', 'Buruh Peternakan',
        'Pembantu Rumah Tangga', 'Tukang Batu', 'Tukang Kayu', 'Tukang Las/Pandai Besi',
        'Tukang Jahit', 'Tukang Listrik', 'Mekanik', 'Juru Masak',
        'Guru', 'Dosen', 'Dokter', 'Bidan', 'Perawat', 'Apoteker',
        'Pengacara', 'Notaris', 'Arsitek', 'Akuntan', 'Konsultan', 'Wartawan', 'Seniman',
        'Pelaut', 'Pilot', 'Peneliti',
        'Perangkat Desa', 'Kepala Desa', 'Imam Masjid', 'Ustadz/Mubaligh', 'Pendeta', 'Pastor',
        'Anggota DPRD', 'Pejabat Negara/Daerah',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $i => $name) {
            Occupation::firstOrCreate(['name' => $name], ['sort_order' => ($i + 1) * 10]);
        }

        Occupation::firstOrCreate(['name' => 'Lainnya'], ['sort_order' => 9000]);
    }
}
