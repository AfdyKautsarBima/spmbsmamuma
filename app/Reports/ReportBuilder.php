<?php

namespace App\Reports;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\ApplicantBilling;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Payment;
use App\Models\UniformHandover;
use App\Models\UniformType;
use App\Models\User;
use App\Services\DocumentChecklist;
use App\Services\EnrollmentService;
use App\Services\EnrollmentStats;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Menyusun isi semua laporan. Filter yang dikenal ($f):
 * from, to (yyyy-mm-dd), school_id, status, scope, referrer_name, referrer_class_name, kategori.
 */
class ReportBuilder
{
    private const HANDED_ACTIVE = '(select count(*) from uniform_handovers h join uniform_types t on t.id = h.uniform_type_id
        where h.applicant_id = applicants.id and t.is_active = 1)';

    public function __construct(
        private EnrollmentService $enrollment,
        private DocumentChecklist $checklist,
        private EnrollmentStats $stats,
    ) {
    }

    public function build(string $key, AcademicYear $year, array $f, User $user): Report
    {
        $f += [
            'from' => null, 'to' => null, 'school_id' => null, 'status' => null, 'scope' => null,
            'referrer_name' => null, 'referrer_class_name' => null, 'referrer_class_filter' => null, 'kategori' => null,
        ];

        return match ($key) {
            'pendaftar'          => $this->pendaftar($year, $f),
            'berkas-kurang'      => $this->berkasKurang($year),
            'rekap-cek-berkas'   => $this->rekapCekBerkas($year),
            'siswa-yatim'        => $this->siswaYatim($year),
            'siswa-berprestasi'  => $this->siswaBerprestasi($year),
            'daftar-ulang'       => $this->daftarUlang($year, $f),
            'pembayaran-harian'  => $this->pembayaranHarian($year, $f),
            'tunggakan'          => $this->tunggakan($year),
            'keuangan'           => $this->keuangan($year, $user),
            'diskon'             => $this->diskon($year),
            'reward'             => $this->reward($year, $f),
            'reward-rincian'     => $this->rewardRincian($year, $f),
            'seragam-belum-ambil' => $this->seragamBelumAmbil($year, $f),
            'seragam-penyerahan' => $this->seragamPenyerahan($year, $f),
            'seragam-ukuran'     => $this->seragamUkuran($year, $f),
            'seragam-mundur'     => $this->seragamMundur($year),
            default              => throw new InvalidArgumentException("Laporan {$key} tidak dikenal."),
        };
    }

    private function sub(AcademicYear $year, ?string $extra = null): string
    {
        return trim('TA '.$year->name.($extra ? ' | '.$extra : ''));
    }

    private function period(?string $from, ?string $to): ?string
    {
        $fmt = fn (string $iso) => date('d/m/Y', strtotime($iso));

        return match (true) {
            $from && $to => 'Periode '.$fmt($from).' sampai '.$fmt($to),
            (bool) $from => 'Mulai '.$fmt($from),
            (bool) $to   => 'Sampai '.$fmt($to),
            default      => null,
        };
    }

    private function validPayments(): \Closure
    {
        return fn ($q) => $q->whereNull('cancelled_at');
    }

    // ------------------------------------------------------------------ pendaftaran

    private function pendaftar(AcademicYear $year, array $f): Report
    {
        $applicants = Applicant::with('school')
            ->where('academic_year_id', $year->id)
            ->when($f['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($f['school_id'], function ($q, $ids) {
                $ids = array_values(array_filter((array) $ids, fn ($id) => ctype_digit((string) $id) && (int) $id > 0));
                if ($ids) {
                    $q->whereIn('school_id', $ids);
                }
            })
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        foreach ($applicants as $i => $a) {
            $rows[] = [
                $i + 1, $a->registration_number, $a->name, $a->gender, $a->birth_place, $a->birth_date->format('d/m/Y'),
                $a->fullAddress(), $a->father_name, $a->mother_name, $a->student_phone ?? '', $a->phone ?? '', $a->nisn ?? '',
                $a->school->name, $a->referrer_name ?? '', $a->referrer_class_name ?? '', $a->created_at->format('d/m/Y'),
            ];
        }

        return (new Report('Daftar Pendaftar', $this->sub($year, $this->period($f['from'], $f['to'])), [$applicants->count().' pendaftar.']))
            ->section(null, array_map(fn ($l) => Report::col($l), [
                'No', 'Nomor', 'Nama', 'L/P', 'Tempat lahir', 'Tanggal lahir', 'Alamat', 'Nama ayah', 'Nama ibu',
                'No. HP siswa', 'No. HP orang tua/wali', 'NISN', 'Asal sekolah', 'Nama pembawa', 'Kelas pembawa', 'Tgl input',
            ]), $rows);
    }

    private function berkasKurang(AcademicYear $year): Report
    {
        $applicants = Applicant::with(['school', 'documents', 'familyDetail'])
            ->where('academic_year_id', $year->id)
            ->where('documents_complete', false)
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        foreach ($applicants as $i => $a) {
            $missing = $this->checklist->missing($this->checklist->statusMap($a), $this->checklist->absentParents($a));
            $rows[] = [$i + 1, $a->registration_number, $a->name, $a->school->name, $a->phone ?? '', implode('; ', $missing)];
        }

        return (new Report('Daftar Berkas Kurang', $this->sub($year), [count($rows).' pendaftar berkasnya belum lengkap.']))
            ->section(null, array_map(fn ($l) => Report::col($l), ['No', 'Nomor', 'Nama', 'Asal sekolah', 'No. HP', 'Berkas yang kurang']), $rows);
    }

    private function rekapCekBerkas(AcademicYear $year): Report
    {
        $applicants = Applicant::with([
            'school',
            'documents',
            'familyDetail.fatherOccupation',
            'familyDetail.motherOccupation',
            'familyDetail.guardianOccupation',
        ])
            ->where('academic_year_id', $year->id)
            ->orderBy('registration_seq')
            ->get();

        $documentTypes = DocumentType::cases();
        $columns = array_map(fn ($type) => Report::col('Status: '.$type->label()), $documentTypes);
        $columns = [
            Report::col('No'),
            Report::col('Nomor pendaftaran'),
            Report::col('Nama siswa'),
            Report::col('Asal sekolah'),
            Report::col('No. HP siswa'),
            Report::col('No. HP orang tua/wali'),
            Report::col('NIK murid'),
            Report::col('Nama ayah'),
            Report::col('NIK ayah'),
            Report::col('Pekerjaan ayah'),
            Report::col('Tahun lahir ayah'),
            Report::col('Nama ibu'),
            Report::col('NIK ibu'),
            Report::col('Pekerjaan ibu'),
            Report::col('Tahun lahir ibu'),
            Report::col('Nama wali'),
            Report::col('NIK wali'),
            Report::col('Pekerjaan wali'),
            Report::col('Tahun lahir wali'),
            Report::col('Nomor KK'),
            Report::col('Nomor KIP'),
            Report::col('Nomor KKS/PKH'),
            Report::col('Kelengkapan berkas'),
            ...$columns,
            Report::col('Jenis peringkat'),
            Report::col('Peringkat'),
        ];

        $rows = [];
        foreach ($applicants as $i => $applicant) {
            $statusMap = $this->checklist->statusMap($applicant);
            $documents = $applicant->documents->keyBy(fn ($document) => $document->type->value);
            $family = $applicant->familyDetail;
            $ranking = $documents->get(DocumentType::SuratPeringkat->value);
            $complete = $this->checklist->isComplete($statusMap, $this->checklist->absentParents($applicant));

            $rows[] = [
                $i + 1,
                $applicant->registration_number,
                $applicant->name,
                $applicant->school->name,
                $applicant->student_phone ?? '',
                $applicant->phone ?? '',
                $family?->student_nik ?? '',
                $applicant->father_name,
                $family?->father_nik ?? '',
                $family?->fatherOccupation?->name ?? '',
                $family?->father_birth_year ?? '',
                $applicant->mother_name,
                $family?->mother_nik ?? '',
                $family?->motherOccupation?->name ?? '',
                $family?->mother_birth_year ?? '',
                $family?->guardian_name ?? '',
                $family?->guardian_nik ?? '',
                $family?->guardianOccupation?->name ?? '',
                $family?->guardian_birth_year ?? '',
                $family?->kk_number ?? '',
                $documents->get(DocumentType::Pkh->value)?->card_number ?? '',
                $documents->get(DocumentType::Kks->value)?->card_number ?? '',
                $complete ? 'Lengkap' : 'Belum lengkap',
                ...array_map(fn ($type) => $statusMap[$type->value]->label(), $documentTypes),
                $ranking?->ranking_type ? ucfirst($ranking->ranking_type) : '',
                $ranking?->rank_position ?? '',
            ];
        }

        return (new Report('Rekap Data Lengkap Siswa', $this->sub($year), [
            count($rows).' pendaftar; status yang tidak memiliki catatan berkas ditampilkan sebagai Belum diterima.',
        ]))->section(null, $columns, $rows);
    }

    private function siswaYatim(AcademicYear $year): Report
    {
        $parentDocumentTypes = [DocumentType::KtpAyah->value, DocumentType::KtpIbu->value];
        $applicants = Applicant::with(['school', 'documents' => fn ($q) => $q->whereIn('type', $parentDocumentTypes)])
            ->where('academic_year_id', $year->id)
            ->whereHas('documents', fn ($q) => $q
                ->whereIn('type', $parentDocumentTypes)
                ->where('status', DocumentStatus::TidakAda->value))
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        foreach ($applicants as $i => $applicant) {
            $statuses = $applicant->documents->keyBy(fn ($document) => $document->type->value);
            $fatherStatus = $statuses->get(DocumentType::KtpAyah->value)?->status ?? DocumentStatus::Belum;
            $motherStatus = $statuses->get(DocumentType::KtpIbu->value)?->status ?? DocumentStatus::Belum;
            $fatherAbsent = $fatherStatus === DocumentStatus::TidakAda;
            $motherAbsent = $motherStatus === DocumentStatus::TidakAda;

            $rows[] = [
                $i + 1,
                $applicant->registration_number,
                $applicant->name,
                $applicant->school->name,
                $fatherAbsent ? 'Tidak ada/almarhum' : $fatherStatus->label(),
                $motherAbsent ? 'Tidak ada/almarhum' : $motherStatus->label(),
                implode(' dan ', array_filter([
                    $fatherAbsent ? 'Ayah' : null,
                    $motherAbsent ? 'Ibu' : null,
                ])),
            ];
        }

        return (new Report('Rekap Siswa Yatim', $this->sub($year), [count($rows).' siswa dengan status KTP ayah atau ibu Tidak ada/almarhum.']))
            ->section(null, array_map(fn ($label) => Report::col($label), [
                'No', 'Nomor pendaftaran', 'Nama siswa', 'Asal sekolah', 'Status KTP ayah', 'Status KTP ibu', 'Yatim dari',
            ]), $rows);
    }

    private function siswaBerprestasi(AcademicYear $year): Report
    {
        $achievementTypes = [DocumentType::SuratPeringkat->value, DocumentType::Sertifikat->value];
        $applicants = Applicant::with(['school', 'documents' => fn ($q) => $q->whereIn('type', $achievementTypes)])
            ->where('academic_year_id', $year->id)
            ->whereHas('documents', fn ($q) => $q
                ->whereIn('type', $achievementTypes)
                ->where('status', DocumentStatus::Diterima->value))
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        foreach ($applicants as $i => $applicant) {
            $documents = $applicant->documents->keyBy(fn ($document) => $document->type->value);
            $ranking = $documents->get(DocumentType::SuratPeringkat->value);
            $certificate = $documents->get(DocumentType::Sertifikat->value);
            $rankingStatus = $ranking?->status ?? DocumentStatus::Belum;
            $certificateStatus = $certificate?->status ?? DocumentStatus::Belum;
            $rankingDetail = $rankingStatus === DocumentStatus::Diterima && $ranking?->rank_position
                ? ucfirst($ranking->ranking_type).' - peringkat '.$ranking->rank_position
                : '-';

            $rows[] = [
                $i + 1,
                $applicant->registration_number,
                $applicant->name,
                $applicant->school->name,
                $rankingStatus->label(),
                $rankingDetail,
                $certificateStatus->label(),
            ];
        }

        return (new Report('Rekap Siswa Berprestasi', $this->sub($year), [
            count($rows).' siswa dengan surat keterangan peringkat atau sertifikat kejuaraan berstatus Diterima.',
        ]))->section(null, array_map(fn ($label) => Report::col($label), [
            'No', 'Nomor pendaftaran', 'Nama siswa', 'Asal sekolah',
            'Status surat peringkat', 'Detail peringkat', 'Status sertifikat kejuaraan',
        ]), $rows);
    }

    // ------------------------------------------------------------------ daftar ulang dan keuangan

    private function daftarUlang(AcademicYear $year, array $f): Report
    {
        $selectedStatuses = array_values(array_intersect((array) $f['status'], ['sudah', 'belum', 'menyicil', 'lunas', 'mundur']));
        if (! $selectedStatuses && is_string($f['status']) && in_array($f['status'], ['sudah', 'belum', 'menyicil', 'lunas', 'mundur'], true)) {
            $selectedStatuses = [$f['status']];
        }
        $wanted = [];
        foreach ($selectedStatuses as $status) {
            $wanted = array_merge($wanted, match ($status) {
                'sudah' => ['menyicil', 'lunas'],
                'belum' => ['terdaftar'],
                default => [$status],
            });
        }
        $wanted = array_values(array_unique($wanted));
        $statusLabels = [
            'sudah' => 'Sudah daftar ulang',
            'belum' => 'Belum daftar ulang',
            'menyicil' => 'Menyicil',
            'lunas' => 'Lunas',
            'mundur' => 'Mengundurkan diri',
        ];
        $label = implode(', ', array_map(fn ($status) => $statusLabels[$status], $selectedStatuses)) ?: null;

        $applicants = Applicant::with(['school', 'billing', 'payments' => $this->validPayments()])
            ->where('academic_year_id', $year->id)
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        $n = 0;
        foreach ($applicants as $a) {
            $s = $this->enrollment->summary($a);
            if ($wanted && ! in_array($s['status']->value, $wanted, true)) {
                continue;
            }
            $first = $a->payments->sortBy('paid_at')->first()?->paid_at;
            $paymentNotes = $a->payments
                ->pluck('note')
                ->filter(fn ($note) => filled($note))
                ->join('; ');
            $rows[] = [
                ++$n, $a->registration_number, $a->name, $a->school->name, $s['status']->label(),
                $s['billing']?->hasDiscount()
                    ? ($s['billing']->discount_type === 'periode' && $s['billing']->discount_percent === 0
                        ? 'Harga '.$s['billing']->discount_label
                        : $s['billing']->discount_label.' '.$s['billing']->discount_percent.'%')
                    : '',
                $s['net'] ?? '', $s['paid'] ?: '', $s['status']->value === 'mundur' ? '' : ($s['remaining'] ?? ''),
                $first?->format('d/m/Y') ?? '', $paymentNotes,
            ];
        }

        return (new Report('Daftar Daftar Ulang', $this->sub($year, $label), [$n.' pendaftar.']))
            ->section(null, [
                Report::col('No'), Report::col('Nomor'), Report::col('Nama'), Report::col('Asal sekolah'), Report::col('Status'),
                Report::col('Diskon'), Report::col('Tagihan bersih', 'rp'), Report::col('Dibayar', 'rp'), Report::col('Sisa', 'rp'),
                Report::col('Pembayaran pertama'), Report::col('Catatan pembayaran'),
            ], $rows);
    }

    private function pembayaranHarian(AcademicYear $year, array $f): Report
    {
        $from = $f['from'] ?: ($f['to'] ? null : today()->toDateString());
        $to = $f['to'] ?: ($f['from'] ? null : today()->toDateString());

        $payments = Payment::with(['applicant', 'receiver'])
            ->where('academic_year_id', $year->id)
            ->when($from, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $detail = [];
        $byDay = [];
        $byUser = [];
        $cancelled = 0;

        foreach ($payments as $p) {
            $detail[] = [
                $p->receipt_number, $p->paid_at->format('d/m/Y H:i'), $p->applicant->registration_number, $p->applicant->name,
                $p->amount, $p->receiver->name, $p->isCancelled() ? 'Dibatalkan' : 'Sah', $p->note ?? '',
            ];

            if ($p->isCancelled()) {
                $cancelled++;
                continue;
            }

            $day = $p->paid_at->format('d/m/Y');
            $byDay[$day] = [($byDay[$day][0] ?? 0) + 1, ($byDay[$day][1] ?? 0) + $p->amount];
            $byUser[$p->receiver->name] = [($byUser[$p->receiver->name][0] ?? 0) + 1, ($byUser[$p->receiver->name][1] ?? 0) + $p->amount];
        }

        $dayRows = [];
        foreach ($byDay as $day => [$count, $sum]) {
            $dayRows[] = [$day, $count, $sum];
        }
        ksort($byUser);
        $userRows = [];
        foreach ($byUser as $name => [$count, $sum]) {
            $userRows[] = [$name, $count, $sum];
        }

        $total = array_sum(array_column($byDay, 1));
        $count = array_sum(array_column($byDay, 0));
        $footer = fn (string $label) => [$label, Report::num($count), Report::rp($total)];

        return (new Report('Rekap Pembayaran Harian', $this->sub($year, $this->period($from, $to)), [
            'Total hanya menghitung pembayaran yang sah. '.$cancelled.' pembayaran dibatalkan ditampilkan pada rincian tetapi tidak dijumlahkan.',
            'Uang dari murid yang kemudian mengundurkan diri tetap dihitung, agar cocok dengan kas.',
        ]))
            ->section('Rincian pembayaran', [
                Report::col('Kwitansi'), Report::col('Waktu'), Report::col('Nomor'), Report::col('Nama'),
                Report::col('Nominal', 'rp'), Report::col('Penerima'), Report::col('Status'), Report::col('Catatan'),
            ], $detail)
            ->section('Total per hari (sah)', [Report::col('Tanggal'), Report::col('Jumlah transaksi', 'int'), Report::col('Total', 'rp')], $dayRows, $footer('Total'))
            ->section('Total per penerima (sah)', [Report::col('Penerima'), Report::col('Jumlah transaksi', 'int'), Report::col('Total', 'rp')], $userRows, $footer('Total'));
    }

    private function tunggakan(AcademicYear $year): Report
    {
        $applicants = Applicant::with(['school', 'billing', 'payments' => $this->validPayments()])
            ->where('academic_year_id', $year->id)
            ->enrollmentStatus('menyicil')
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        $sumNet = $sumPaid = $sumRemaining = 0;
        foreach ($applicants as $i => $a) {
            $s = $this->enrollment->summary($a);
            $last = $a->payments->sortByDesc('paid_at')->first()?->paid_at;
            $rows[] = [$i + 1, $a->registration_number, $a->name, $a->school->name, $s['net'], $s['paid'], $s['remaining'], $last?->format('d/m/Y') ?? ''];
            $sumNet += $s['net'];
            $sumPaid += $s['paid'];
            $sumRemaining += $s['remaining'];
        }

        return (new Report('Daftar Tunggakan', $this->sub($year), [
            count($rows).' murid masih menyicil. Murid yang mengundurkan diri tidak dihitung.',
        ]))->section(null, [
            Report::col('No'), Report::col('Nomor'), Report::col('Nama'), Report::col('Asal sekolah'),
            Report::col('Tagihan bersih', 'rp'), Report::col('Dibayar', 'rp'), Report::col('Sisa', 'rp'), Report::col('Pembayaran terakhir'),
        ], $rows, ['Total', '', '', '', Report::rp($sumNet), Report::rp($sumPaid), Report::rp($sumRemaining), '']);
    }

    private function keuangan(AcademicYear $year, User $user): Report
    {
        $s = $this->stats->forYear($year);
        $total = Applicant::where('academic_year_id', $year->id)->count();
        $m = $s['money'];

        $summary = [
            ['Jumlah pendaftar', Report::num($total)],
            ['Terdaftar (belum membayar)', Report::num($s['counts']['terdaftar'])],
            ['Daftar ulang, menyicil', Report::num($s['counts']['menyicil'])],
            ['Daftar ulang, lunas', Report::num($s['counts']['lunas'])],
            ['Mengundurkan diri', Report::num($s['counts']['mundur'])],
            ['Tagihan kotor (murid yang sudah membayar, tidak mundur)', Report::rp($m['gross'])],
            ['Total diskon', Report::rp($m['discount'])],
            ['Tagihan bersih', Report::rp($m['net'])],
            ['Total diterima dari murid tidak mundur', Report::rp($m['paid'])],
            ['Sisa tagihan', Report::rp($m['remaining'])],
            ['Murid kelebihan bayar', Report::num($s['overpaid'])],
            ['Uang dari murid yang mundur', Report::rp($m['withdrawn_paid'])],
            ['Total uang masuk (semua pembayaran sah)', Report::rp($m['paid'] + $m['withdrawn_paid'])],
        ];

        $showRefund = $user->isAdmin();
        $withdrawn = Applicant::with(['payments' => $this->validPayments()])
            ->where('academic_year_id', $year->id)->whereNotNull('withdrawn_at')->orderBy('registration_seq')->get()
            ->filter(fn ($a) => $a->payments->sum('amount') > 0);

        $rows = [];
        foreach ($withdrawn as $a) {
            $row = [$a->registration_number, $a->name, (int) $a->payments->sum('amount'), $a->withdrawn_at->format('d/m/Y'), $a->withdrawn_reason ?? ''];
            if ($showRefund) {
                $row[] = $a->refund_note ?? '';
            }
            $rows[] = $row;
        }
        $columns = [Report::col('Nomor'), Report::col('Nama'), Report::col('Dibayar', 'rp'), Report::col('Tanggal mundur'), Report::col('Alasan')];
        if ($showRefund) {
            $columns[] = Report::col('Catatan pengembalian');
        }

        return (new Report('Laporan Keuangan Daftar Ulang', $this->sub($year), [
            'Pengembalian dana diurus di luar sistem, sehingga angka di atas belum dikurangi pengembalian.',
        ]))
            ->section('Ringkasan', [Report::col('Uraian'), Report::col('Nilai', 'text')], $summary)
            ->section('Murid mengundurkan diri yang sudah membayar', $columns, $rows);
    }

    private function diskon(AcademicYear $year): Report
    {
        $billings = ApplicantBilling::with('applicant.school')
            ->where('academic_year_id', $year->id)
            ->where(fn ($q) => $q
                ->where('discount_percent', '>', 0)
                ->orWhereColumn('net_bill', '<', 'fee_snapshot'))
            ->get()
            ->filter(fn ($b) => $b->applicant && ! $b->applicant->trashed())
            ->sortBy(fn ($b) => $b->applicant->registration_seq)
            ->values();

        $groups = [];
        $rows = [];
        foreach ($billings as $i => $b) {
            $cut = $b->fee_snapshot - $b->net_bill;
            $type = $b->discount_type === 'manual' ? 'Khusus (admin)' : 'Periode';
            $discount = $b->discount_percent > 0 ? $b->discount_percent.'%' : 'Rp '.number_format($b->net_bill, 0, ',', '.');
            $gk = $type.'|'.$b->discount_label.'|'.$discount;
            $groups[$gk] = [
                'type' => $type, 'label' => $b->discount_label, 'discount' => $discount,
                'count' => ($groups[$gk]['count'] ?? 0) + 1, 'cut' => ($groups[$gk]['cut'] ?? 0) + $cut,
            ];
            $rows[] = [
                $i + 1, $b->applicant->registration_number, $b->applicant->name, $b->applicant->school->name, $type, $b->discount_label,
                $discount, $b->fee_snapshot, $cut, $b->net_bill, $b->reason ?? '',
            ];
        }

        ksort($groups);
        $summary = array_map(fn ($g) => [$g['type'], $g['label'], $g['discount'], $g['count'], $g['cut']], array_values($groups));
        $footer = ['Total', '', '', Report::num(array_sum(array_column($groups, 'count'))), Report::rp(array_sum(array_column($groups, 'cut')))];

        return (new Report('Rekap Diskon', $this->sub($year), [count($rows).' murid berdiskon.']))
            ->section('Rekap per periode / jenis diskon', [
                Report::col('Jenis'), Report::col('Nama'), Report::col('Diskon / tagihan periode'), Report::col('Jumlah murid', 'int'), Report::col('Total potongan', 'rp'),
            ], $summary, $groups ? $footer : null)
            ->section('Daftar murid berdiskon', [
                Report::col('No'), Report::col('Nomor'), Report::col('Nama'), Report::col('Asal sekolah'), Report::col('Jenis'), Report::col('Nama diskon'),
                Report::col('Diskon'), Report::col('Biaya', 'rp'), Report::col('Potongan', 'rp'), Report::col('Tagihan bersih', 'rp'), Report::col('Alasan'),
            ], $rows);
    }

    // ------------------------------------------------------------------ reward pembawa

    /** Kategori reward: mundur (batal), reward (sudah bayar), atau belum (belum bayar). */
    private function rewardCategory(bool $withdrawn, int $paid): string
    {
        return $withdrawn ? 'mundur' : ($paid > 0 ? 'reward' : 'belum');
    }

    private function reward(AcademicYear $year, array $f): Report
    {
        $applicants = Applicant::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('referrer_name')
            ->when(
                $f['referrer_class_filter'] === '__tanpa_kelas__',
                fn ($q) => $q->where(fn ($query) => $query->whereNull('referrer_class_name')->orWhere('referrer_class_name', '')),
                fn ($q) => $q->when(
                    $f['referrer_class_filter'],
                    fn ($query, $className) => $query->whereRaw('LOWER(referrer_class_name) = ?', [mb_strtolower($className)]),
                ),
            )
            ->selectRaw('applicants.id, applicants.referrer_name, applicants.referrer_class_name, applicants.withdrawn_at,
                (select coalesce(sum(p.amount), 0) from payments p where p.applicant_id = applicants.id and p.cancelled_at is null) as paid')
            ->orderBy('applicants.id')
            ->get();

        $perReferrer = [];
        foreach ($applicants as $a) {
            $key = json_encode([
                mb_strtolower(trim($a->referrer_name)),
                mb_strtolower(trim((string) $a->referrer_class_name)),
            ], JSON_THROW_ON_ERROR);
            $cat = $this->rewardCategory($a->withdrawn_at !== null, (int) $a->paid);
            $perReferrer[$key]['name'] = $a->referrer_name;
            $perReferrer[$key]['class_name'] = $a->referrer_class_name;
            $perReferrer[$key][$cat] = ($perReferrer[$key][$cat] ?? 0) + 1;
        }

        $link = fn (int $count, array $referrer, string $cat) => $count > 0
            ? Report::link($count, route('reports.show', [
                'key' => 'reward-rincian',
                'referrer_name' => $referrer['name'],
                'referrer_class_name' => $referrer['class_name'],
                'kategori' => $cat,
                'year' => $year->id,
            ]), 'int')
            : 0;

        $lines = [];
        foreach ($perReferrer as $c) {
            $lines[] = $c + ['reward' => 0, 'belum' => 0, 'mundur' => 0];
        }
        usort($lines, fn ($a, $b) => [$b['reward'], $a['name'], $a['class_name']] <=> [$a['reward'], $b['name'], $b['class_name']]);

        $rows = [];
        $sum = ['all' => 0, 'reward' => 0, 'belum' => 0, 'mundur' => 0];
        foreach ($lines as $i => $l) {
            $all = $l['reward'] + $l['belum'] + $l['mundur'];
            $referrer = ['name' => $l['name'], 'class_name' => $l['class_name']];
            $rows[] = [
                $i + 1, $l['name'], $l['class_name'] ?? '', $link($all, $referrer, 'semua'), $link($l['reward'], $referrer, 'reward'),
                $link($l['belum'], $referrer, 'belum'), $link($l['mundur'], $referrer, 'mundur'),
            ];
            $sum['all'] += $all;
            $sum['reward'] += $l['reward'];
            $sum['belum'] += $l['belum'];
            $sum['mundur'] += $l['mundur'];
        }

        $withoutReferrer = Applicant::where('academic_year_id', $year->id)->whereNull('referrer_name')->count();

        return (new Report('Rekap Reward Pembawa', $this->sub($year), [
            'Dihitung reward: murid yang sudah membayar (menyicil atau lunas) dan tidak mengundurkan diri.',
            'Murid yang mengundurkan diri membatalkan reward. Nominal reward dan status pembayarannya diurus di luar sistem.',
            $withoutReferrer.' pendaftar tidak memiliki pembawa dan tidak termasuk rekap ini.',
        ]))->section(null, [
            Report::col('No'), Report::col('Nama pembawa'), Report::col('Kelas'), Report::col('Total dibawa', 'int'), Report::col('Dihitung reward', 'int'),
            Report::col('Belum bayar', 'int'), Report::col('Mundur (batal)', 'int'),
        ], $rows, ['Total', '', '', Report::num($sum['all']), Report::num($sum['reward']), Report::num($sum['belum']), Report::num($sum['mundur'])]);
    }

    private function rewardRincian(AcademicYear $year, array $f): Report
    {
        $cat = in_array($f['kategori'], ['reward', 'belum', 'mundur'], true) ? $f['kategori'] : 'semua';
        $label = ['semua' => 'Semua murid', 'reward' => 'Dihitung reward', 'belum' => 'Belum bayar', 'mundur' => 'Mundur (reward batal)'][$cat];

        $applicants = Applicant::with(['school', 'billing', 'payments' => $this->validPayments()])
            ->where('academic_year_id', $year->id)
            ->whereRaw('LOWER(referrer_name) = ?', [mb_strtolower($f['referrer_name'])])
            ->when(
                $f['referrer_class_name'] === null || $f['referrer_class_name'] === '',
                fn ($q) => $q->where(fn ($query) => $query->whereNull('referrer_class_name')->orWhere('referrer_class_name', '')),
                fn ($q) => $q->whereRaw('LOWER(referrer_class_name) = ?', [mb_strtolower($f['referrer_class_name'])]),
            )
            ->orderBy('registration_seq')->get();

        $rows = [];
        foreach ($applicants as $a) {
            $s = $this->enrollment->summary($a);
            if ($cat !== 'semua' && $this->rewardCategory($a->withdrawn_at !== null, $s['paid']) !== $cat) {
                continue;
            }
            $rows[] = [count($rows) + 1, $a->registration_number, $a->name, $a->school->name, $s['status']->label(), $s['paid'] ?: ''];
        }

        $title = 'Rincian Pembawa: '.$f['referrer_name'].($f['referrer_class_name'] ? ' ('.$f['referrer_class_name'].')' : '');

        return (new Report($title, $this->sub($year, $label), [count($rows).' murid.']))
            ->section(null, [
                Report::col('No'), Report::col('Nomor'), Report::col('Nama'), Report::col('Asal sekolah'), Report::col('Status'), Report::col('Dibayar', 'rp'),
            ], $rows);
    }

    // ------------------------------------------------------------------ seragam

    private function seragamBelumAmbil(AcademicYear $year, array $f): Report
    {
        $selectedScopes = array_values(array_intersect((array) $f['scope'], ['belum', 'belum_lengkap']));
        if (! $selectedScopes && is_string($f['scope']) && in_array($f['scope'], ['belum', 'belum_lengkap'], true)) {
            $selectedScopes = [$f['scope']];
        }
        $incomplete = in_array('belum_lengkap', $selectedScopes, true);
        $activeTypes = UniformType::query()
            ->where('academic_year_id', $year->id)
            ->where('is_active', true)
            ->ordered()
            ->get();
        $typeCount = $activeTypes->count();

        $applicants = Applicant::with(['school', 'billing', 'payments' => $this->validPayments(), 'uniformEntitlement', 'uniformHandovers.type', 'uniformSizes.size'])
            ->where('academic_year_id', $year->id)
            ->whereNull('withdrawn_at')
            ->whereHas('uniformEntitlement')
            ->when(
                $incomplete,
                fn ($q) => $q->whereRaw(self::HANDED_ACTIVE.' < ?', [max($typeCount, 1)]),
                fn ($q) => $q->whereDoesntHave('uniformHandovers'),
            )
            ->orderBy('registration_seq')
            ->get();

        $typeNames = UniformType::where('academic_year_id', $year->id)->pluck('name', 'id');

        $rows = [];
        foreach ($applicants as $i => $a) {
            $sizes = $a->uniformSizes->map(fn ($s) => ($typeNames[$s->uniform_type_id] ?? '-').': '.$s->size->name)->join(', ');
            $handedTypeIds = $a->uniformHandovers->pluck('uniform_type_id');
            $notHandedOver = $activeTypes
                ->reject(fn ($type) => $handedTypeIds->contains($type->id))
                ->pluck('name')
                ->join(', ');
            $row = [$i + 1, $a->registration_number, $a->name, $a->school->name, (int) $a->payments->sum('amount'), $a->uniformEntitlement->granted_at->format('d/m/Y'), $sizes, $notHandedOver];
            if ($incomplete) {
                $row[] = $a->uniformHandovers->count().'/'.$typeCount.($a->uniformHandovers->isNotEmpty() ? ': '.$a->uniformHandovers->map(fn ($h) => $h->type->name)->join(', ') : '');
            }
            $rows[] = $row;
        }

        $columns = [Report::col('No'), Report::col('Nomor'), Report::col('Nama'), Report::col('Asal sekolah'), Report::col('Total dibayar', 'rp'), Report::col('Berhak sejak'), Report::col('Ukuran dicatat')];
        $columns[] = Report::col('Seragam belum diambil');
        if ($incomplete) {
            $columns[] = Report::col('Sudah diambil');
        }

        $label = match (true) {
            $incomplete => 'Belum lengkap (termasuk sebagian)',
            in_array('belum', $selectedScopes, true) => 'Belum mengambil sama sekali',
            default => null,
        };

        return (new Report('Daftar Berhak Seragam, Belum Diambil', $this->sub($year, $label), [count($rows).' murid.']))
            ->section(null, $columns, $rows);
    }

    private function seragamPenyerahan(AcademicYear $year, array $f): Report
    {
        $handovers = UniformHandover::with('type')
            ->whereIn('applicant_id', Applicant::where('academic_year_id', $year->id)->select('id'))
            ->when($f['from'], fn ($q, $d) => $q->whereDate('handed_at', '>=', $d))
            ->when($f['to'], fn ($q, $d) => $q->whereDate('handed_at', '<=', $d))
            ->orderBy('handed_at')
            ->get();

        $perType = [];
        $perDay = [];
        foreach ($handovers as $h) {
            $perType[$h->type->name] = ($perType[$h->type->name] ?? 0) + 1;
            $day = $h->handed_at->format('Y-m-d');
            $perDay[$day][$h->type->name] = ($perDay[$day][$h->type->name] ?? 0) + 1;
        }

        ksort($perType);
        $typeRows = [];
        foreach ($perType as $name => $count) {
            $typeRows[] = [$name, $count];
        }

        ksort($perDay);
        $dayRows = [];
        foreach ($perDay as $day => $types) {
            ksort($types);
            foreach ($types as $name => $count) {
                $dayRows[] = [date('d/m/Y', strtotime($day)), $name, $count];
            }
        }

        return (new Report('Rekap Penyerahan Seragam', $this->sub($year, $this->period($f['from'], $f['to'])), [$handovers->count().' penyerahan.']))
            ->section('Per jenis seragam', [Report::col('Jenis seragam'), Report::col('Jumlah diserahkan', 'int')], $typeRows, ['Total', Report::num($handovers->count())])
            ->section('Per hari dan jenis', [Report::col('Tanggal'), Report::col('Jenis seragam'), Report::col('Jumlah', 'int')], $dayRows);
    }

    private function seragamUkuran(AcademicYear $year, array $f): Report
    {
        $selectedScopes = array_values(array_intersect((array) $f['scope'], ['semua', 'belum_diambil']));
        if (! $selectedScopes && is_string($f['scope']) && in_array($f['scope'], ['semua', 'belum_diambil'], true)) {
            $selectedScopes = [$f['scope']];
        }
        $onlyNotPicked = in_array('belum_diambil', $selectedScopes, true) && ! in_array('semua', $selectedScopes, true);
        $report = new Report('Rekap Kebutuhan Ukuran Seragam', $this->sub($year, $onlyNotPicked ? 'Hanya yang belum diambil' : 'Semua murid berhak'), [
            'Dihitung dari murid berhak yang tidak mengundurkan diri.',
        ]);

        $types = UniformType::where('academic_year_id', $year->id)->where('is_active', true)->where('needs_size', true)->ordered()->get();

        foreach ($types as $type) {
            $base = DB::table('uniform_entitlements as e')
                ->join('applicants as a', 'a.id', '=', 'e.applicant_id')
                ->where('e.academic_year_id', $year->id)
                ->whereNull('a.withdrawn_at')
                ->whereNull('a.deleted_at');

            if ($onlyNotPicked) {
                $base->whereNotExists(fn ($q) => $q->selectRaw('1')->from('uniform_handovers as h')
                    ->whereColumn('h.applicant_id', 'a.id')->where('h.uniform_type_id', $type->id));
            }

            $entitled = (clone $base)->count();
            $recorded = (clone $base)
                ->join('applicant_uniform_sizes as s', function ($join) use ($type) {
                    $join->on('s.applicant_id', '=', 'a.id')->where('s.uniform_type_id', '=', $type->id);
                })
                ->join('uniform_sizes as z', 'z.id', '=', 's.uniform_size_id')
                ->groupBy('z.id', 'z.name', 'z.sort_order')
                ->orderBy('z.sort_order')->orderBy('z.id')
                ->selectRaw('z.name as size_name, count(*) as total')
                ->get();

            $rows = $recorded->map(fn ($r) => [$r->size_name, (int) $r->total])->all();
            $sum = (int) $recorded->sum('total');
            if ($entitled - $sum > 0) {
                $rows[] = ['Ukuran belum dicatat', $entitled - $sum];
            }

            $report->section($type->name, [Report::col('Ukuran'), Report::col('Jumlah murid', 'int')], $rows, ['Total murid berhak', Report::num($entitled)]);
        }

        if ($types->isEmpty()) {
            $report->notes[] = 'Belum ada jenis seragam aktif yang memakai ukuran.';
        }

        return $report;
    }

    private function seragamMundur(AcademicYear $year): Report
    {
        $applicants = Applicant::with(['school', 'uniformHandovers.type'])
            ->where('academic_year_id', $year->id)
            ->whereNotNull('withdrawn_at')
            ->whereHas('uniformHandovers')
            ->orderBy('registration_seq')
            ->get();

        $rows = [];
        foreach ($applicants as $i => $a) {
            $rows[] = [
                $i + 1, $a->registration_number, $a->name, $a->school->name, $a->withdrawn_at->format('d/m/Y'),
                $a->uniformHandovers->map(fn ($h) => $h->type->name)->join(', '), $a->withdrawn_reason ?? '',
            ];
        }

        return (new Report('Murid Mundur dengan Seragam Sudah Diambil', $this->sub($year), [
            count($rows).' murid. Pengembalian seragam diurus di luar sistem.',
        ]))->section(null, [
            Report::col('No'), Report::col('Nomor'), Report::col('Nama'), Report::col('Asal sekolah'), Report::col('Tanggal mundur'),
            Report::col('Seragam yang sudah diambil'), Report::col('Alasan mundur'),
        ], $rows);
    }
}
