<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\School;
use App\Reports\ReportBuilder;
use App\Support\DateInput;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReportController extends Controller
{
    private const ADMIN = ['admin'];
    private const REG = ['admin', 'pendaftaran'];
    private const CASH = ['admin', 'daftar_ulang'];
    private const UNIFORM = ['admin', 'seragam'];

    /** Daftar laporan: key => judul, kelompok, deskripsi, peran yang boleh membuka. */
    private const REPORTS = [
        'pendaftar'           => ['Daftar Pendaftar', 'Pendaftaran', 'Semua pendaftar tahun ajaran, bisa disaring per tanggal input dan asal sekolah.', self::REG],
        'berkas-kurang'       => ['Daftar Berkas Kurang', 'Pendaftaran', 'Pendaftar yang berkasnya belum lengkap beserta berkas yang kurang, untuk dihubungi.', self::REG],
        'siswa-yatim'         => ['Rekap Siswa Yatim', 'Pendaftaran', 'Siswa dengan status KTP ayah atau ibu Tidak ada/almarhum.', self::ADMIN],
        'daftar-ulang'        => ['Daftar Daftar Ulang', 'Daftar ulang dan keuangan', 'Status daftar ulang setiap murid: sudah, belum, menyicil, lunas, atau mundur.', self::CASH],
        'pembayaran-harian'   => ['Rekap Pembayaran Harian', 'Daftar ulang dan keuangan', 'Uang masuk per hari dan per panitia penerima, untuk mencocokkan kas.', self::CASH],
        'tunggakan'           => ['Daftar Tunggakan', 'Daftar ulang dan keuangan', 'Murid yang masih menyicil beserta sisa tagihannya.', self::CASH],
        'keuangan'            => ['Laporan Keuangan', 'Daftar ulang dan keuangan', 'Tagihan kotor, diskon, tagihan bersih, diterima, sisa, dan uang dari murid yang mundur.', self::CASH],
        'diskon'              => ['Rekap Diskon', 'Daftar ulang dan keuangan', 'Jumlah murid dan total potongan per periode diskon, serta daftar murid berdiskon.', self::CASH],
        'reward'              => ['Rekap Reward Pembawa', 'Reward', 'Jumlah murid per pembawa yang dihitung reward (sudah bayar, tidak mundur).', self::ADMIN],
        'reward-rincian'      => ['Rincian Pembawa', 'Reward', 'Daftar murid di balik angka pada rekap reward.', self::ADMIN, true],
        'seragam-belum-ambil' => ['Daftar Berhak Seragam, Belum Diambil', 'Seragam', 'Murid yang berhak tetapi belum mengambil seragam, untuk pengumuman.', self::UNIFORM],
        'seragam-penyerahan'  => ['Rekap Penyerahan Seragam', 'Seragam', 'Jumlah seragam yang diserahkan per jenis dan per hari.', self::UNIFORM],
        'seragam-ukuran'      => ['Rekap Kebutuhan Ukuran Seragam', 'Seragam', 'Jumlah murid berhak per ukuran untuk seragam yang memakai ukuran, untuk pemesanan.', self::UNIFORM],
        'seragam-mundur'      => ['Murid Mundur, Seragam Sudah Diambil', 'Seragam', 'Murid yang mengundurkan diri tetapi seragamnya sudah diambil.', self::UNIFORM],
    ];

    public function __construct(private ReportBuilder $builder)
    {
    }

    public function index(Request $request): View
    {
        $role = $request->user()->role->value;
        $groups = [];

        foreach (self::REPORTS as $key => $def) {
            if (($def[4] ?? false) || ! in_array($role, $def[3], true)) {
                continue;
            }
            $groups[$def[1]][] = ['key' => $key, 'title' => $def[0], 'description' => $def[2]];
        }

        return view('reports.index', ['groups' => $groups]);
    }

    public function show(Request $request, string $key): View|Response
    {
        $def = self::REPORTS[$key] ?? null;
        abort_if($def === null, 404);

        $user = $request->user();
        abort_unless(in_array($user->role->value, $def[3], true), 403);

        $active = AcademicYear::active();
        $year = $user->isAdmin() && $request->filled('year') ? (AcademicYear::find($request->integer('year')) ?? $active) : $active;
        abort_if(! $year, 403, 'Belum ada tahun ajaran aktif.');

        $filters = [
            'from'      => DateInput::iso($request->query('from')),
            'to'        => DateInput::iso($request->query('to')),
            'school_id' => $request->integer('school_id') ?: null,
            'status'    => $request->query('status') ?: null,
            'scope'     => $request->query('scope') ?: null,
            'referrer'  => $request->integer('referrer') ?: null,
            'kategori'  => $request->query('kategori') ?: null,
        ];

        if ($key === 'reward-rincian') {
            abort_if(! $filters['referrer'], 404);
        }

        $report = $this->builder->build($key, $year, $filters, $user);

        return match ($request->query('format')) {
            'xlsx'  => $this->download($report, $key, $year),
            'print' => view('reports.print', ['report' => $report]),
            default => view('reports.show', [
                'report'      => $report,
                'key'         => $key,
                'year'        => $year,
                'years'       => $user->isAdmin() ? AcademicYear::orderByDesc('name')->get() : collect(),
                'filterDefs'  => $this->filterDefinitions($key),
            ]),
        };
    }

    private function download($report, string $key, AcademicYear $year): Response
    {
        $name = 'laporan-'.Str::slug($key.' '.str_replace('/', '-', $year->name)).'-'.now()->format('Ymd').'.xlsx';

        return response(XlsxWriter::toString($report), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /** @return list<array{name: string, label: string, type: string, options?: array<string,string>}> */
    private function filterDefinitions(string $key): array
    {
        $from = ['name' => 'from', 'label' => 'Dari tanggal', 'type' => 'date'];
        $to = ['name' => 'to', 'label' => 'Sampai tanggal', 'type' => 'date'];

        return match ($key) {
            'pendaftar' => [$from, $to, [
                'name' => 'school_id', 'label' => 'Asal sekolah', 'type' => 'select',
                'options' => ['' => 'Semua sekolah'] + School::orderBy('name')->pluck('name', 'id')->all(),
            ]],
            'daftar-ulang' => [[
                'name' => 'status', 'label' => 'Status', 'type' => 'select',
                'options' => ['' => 'Semua', 'sudah' => 'Sudah daftar ulang (menyicil dan lunas)', 'belum' => 'Belum daftar ulang',
                    'menyicil' => 'Menyicil', 'lunas' => 'Lunas', 'mundur' => 'Mengundurkan diri'],
            ]],
            'pembayaran-harian', 'seragam-penyerahan' => [$from, $to],
            'seragam-belum-ambil' => [[
                'name' => 'scope', 'label' => 'Cakupan', 'type' => 'select',
                'options' => ['belum' => 'Belum mengambil sama sekali', 'belum_lengkap' => 'Belum lengkap (termasuk sebagian)'],
            ]],
            'seragam-ukuran' => [[
                'name' => 'scope', 'label' => 'Cakupan', 'type' => 'select',
                'options' => ['semua' => 'Semua murid berhak', 'belum_diambil' => 'Hanya yang belum diambil'],
            ]],
            default => [],
        };
    }
}
