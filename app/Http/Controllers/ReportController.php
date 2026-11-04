<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\School;
use App\Reports\ReportBuilder;
use App\Support\DateInput;
use App\Support\ReferrerClass;
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
        'rekap-cek-berkas'    => ['Rekap Data Lengkap Siswa', 'Pendaftaran', 'Identitas siswa dan keluarga serta status setiap berkas seluruh pendaftar.', self::REG],
        'siswa-yatim'         => ['Rekap Siswa Yatim', 'Pendaftaran', 'Siswa dengan status KTP ayah atau ibu Tidak ada/almarhum.', self::ADMIN],
        'siswa-berprestasi'   => ['Rekap Siswa Berprestasi', 'Pendaftaran', 'Siswa dengan surat keterangan peringkat atau sertifikat kejuaraan berstatus Diterima.', self::ADMIN],
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
            'school_id' => $this->queryValues($request->query('school_id'), true),
            'status'    => $this->queryValues($request->query('status')),
            'scope'     => $this->queryValues($request->query('scope')),
            'referrer_name' => is_string($request->query('referrer_name')) ? trim($request->query('referrer_name')) : null,
            'referrer_class_name' => is_string($request->query('referrer_class_name')) ? trim($request->query('referrer_class_name')) : null,
            'referrer_class_filter' => is_string($request->query('referrer_class_filter')) ? trim($request->query('referrer_class_filter')) : null,
            'kategori'  => $request->query('kategori') ?: null,
        ];

        if ($key === 'reward-rincian') {
            abort_if(! $filters['referrer_name'], 404);
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

    private function queryValues(mixed $value, bool $integers = false): array|string|null
    {
        if (! is_array($value)) {
            if (! is_scalar($value) || (string) $value === '') {
                return null;
            }

            return $integers
                ? (ctype_digit((string) $value) && (int) $value > 0 ? (string) (int) $value : null)
                : (string) $value;
        }

        $values = array_map('strval', array_filter($value, fn ($item) => is_scalar($item) && (string) $item !== ''));
        if ($integers) {
            $values = array_filter($values, fn ($item) => ctype_digit($item) && (int) $item > 0);
            $values = array_map(fn ($item) => (string) (int) $item, $values);
        }

        return array_values(array_unique($values)) ?: null;
    }

    /** @return list<array{name: string, label: string, type: string, options?: array<string,string>, hint?: string, default?: string}> */
    private function filterDefinitions(string $key): array
    {
        $from = ['name' => 'from', 'label' => 'Dari tanggal', 'type' => 'date'];
        $to = ['name' => 'to', 'label' => 'Sampai tanggal', 'type' => 'date'];

        return match ($key) {
            'pendaftar' => [$from, $to, [
                'name' => 'school_id', 'label' => 'Asal sekolah', 'type' => 'multi-select',
                'hint' => 'Pilih satu atau beberapa sekolah. Jika tidak ada yang dipilih, semua sekolah ditampilkan.',
                'options' => School::orderBy('name')->pluck('name', 'id')->all(),
            ]],
            'daftar-ulang' => [[
                'name' => 'status', 'label' => 'Status', 'type' => 'multi-select',
                'hint' => 'Pilih satu atau beberapa status. Jika tidak ada yang dipilih, semua status ditampilkan.',
                'options' => ['sudah' => 'Sudah daftar ulang (menyicil dan lunas)', 'belum' => 'Belum daftar ulang',
                    'menyicil' => 'Menyicil', 'lunas' => 'Lunas', 'mundur' => 'Mengundurkan diri'],
            ]],
            'reward' => [$this->rewardClassFilter()],
            'pembayaran-harian', 'seragam-penyerahan' => [$from, $to],
            'seragam-belum-ambil' => [[
                'name' => 'scope', 'label' => 'Cakupan', 'type' => 'multi-select', 'default' => 'belum',
                'hint' => 'Pilih satu atau beberapa cakupan. Jika memilih keduanya, ditampilkan semua yang belum lengkap.',
                'options' => ['belum' => 'Belum mengambil sama sekali', 'belum_lengkap' => 'Belum lengkap (termasuk sebagian)'],
            ]],
            'seragam-ukuran' => [[
                'name' => 'scope', 'label' => 'Cakupan', 'type' => 'multi-select', 'default' => 'semua',
                'hint' => 'Pilih satu atau beberapa cakupan. Memilih “Semua murid berhak” menampilkan semua.',
                'options' => ['semua' => 'Semua murid berhak', 'belum_diambil' => 'Hanya yang belum diambil'],
            ]],
            default => [],
        };
    }

    private function rewardClassFilter(): array
    {
        $options = ['' => 'Semua kelas'];
        foreach (ReferrerClass::OPTIONS as $className) {
            $options[$className] = $className;
        }

        $otherClasses = Applicant::query()
            ->whereNotNull('referrer_class_name')
            ->whereNotIn('referrer_class_name', ReferrerClass::OPTIONS)
            ->distinct()
            ->orderBy('referrer_class_name')
            ->pluck('referrer_class_name')
            ->all();

        foreach ($otherClasses as $className) {
            $options[$className] = $className;
        }
        $options['__tanpa_kelas__'] = 'Tanpa kelas';

        return [
            'name' => 'referrer_class_filter',
            'label' => 'Kelas pembawa',
            'type' => 'select',
            'options' => $options,
        ];
    }
}
