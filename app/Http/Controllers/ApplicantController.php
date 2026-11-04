<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicantRequest;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\School;
use App\Services\ApplicantChecks;
use App\Services\EnrollmentService;
use App\Services\WhatsAppMessageTemplate;
use App\Support\DateInput;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ApplicantController extends Controller
{
    public function __construct(private ApplicantChecks $checks)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $active = AcademicYear::active();
        $year = $active;

        // Hanya admin yang boleh melihat tahun ajaran lain (arsip, hanya baca).
        if ($user->isAdmin() && $request->filled('year')) {
            $year = AcademicYear::find($request->integer('year')) ?? $active;
        }

        $query = Applicant::query()->with('school');

        if ($user->isAdmin() && $request->status === 'dihapus') {
            $query->onlyTrashed();
        }

        $applicants = $query
            ->when($year, fn ($q, $y) => $q->where('academic_year_id', $y->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")
                ->orWhere('father_name', 'like', "%{$s}%")
                ->orWhere('mother_name', 'like', "%{$s}%")))
            ->when($request->school_id, fn ($q, $id) => $q->where('school_id', $id))
            ->when(DateInput::iso($request->date), fn ($q, $d) => $q->whereDate('created_at', $d))
            ->orderByDesc('registration_seq')
            ->paginate(25)
            ->withQueryString();

        return view('applicants.index', [
            'applicants' => $applicants,
            'year'       => $year,
            'readOnly'   => ! $year?->is_active,
            'years'      => $user->isAdmin() ? AcademicYear::orderByDesc('name')->get() : collect(),
            'schools'    => School::orderBy('name')->get(['id', 'name', 'regency_name']),
        ]);
    }

    public function create(): View
    {
        $year = AcademicYear::active();
        abort_if(! $year, 403, 'Belum ada tahun ajaran aktif. Minta admin mengaktifkannya.');

        return view('applicants.form', [
            'applicant' => new Applicant(),
            'year'      => $year,
            'hint'      => $this->checks->hint($year->id),
            'schools'   => $this->schoolOptions(null),
        ]);
    }

    public function store(ApplicantRequest $request): RedirectResponse
    {
        $year = AcademicYear::active();
        $payload = $request->payload();

        $warnings = $this->checks->warnings($year->id, $payload['registration_seq'], $payload['person_key'], null, true);
        if ($warnings && ! $request->boolean('confirm_warnings')) {
            return back()->withInput()->with('warnings', $warnings);
        }

        try {
            $applicant = Applicant::create($payload + [
                'academic_year_id' => $year->id,
                'created_by'       => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['registration_number' => 'Nomor ini baru saja dipakai pendaftar lain.']);
        }

        ActivityLog::record(
            'applicant.created',
            "Pendaftar {$applicant->registration_number} {$applicant->name} ditambahkan",
            $applicant,
            null,
            Arr::only($payload, ['registration_number', 'name', 'school_id', 'referrer_name', 'referrer_class_name']),
        );

        // Kembali ke form kosong agar input berikutnya bisa langsung dilanjutkan.
        return redirect()->route('applicants.create')->with('saved', [
            'id' => $applicant->id, 'number' => $applicant->registration_number, 'name' => $applicant->name,
        ]);
    }

    public function show(Request $request, Applicant $applicant): View
    {
        abort_if($applicant->trashed() && ! $request->user()->isAdmin(), 404);

        $applicant->load(['school', 'academicYear', 'creator', 'billing', 'payments']);
        $enrollmentSummary = app(EnrollmentService::class)->summary($applicant);

        $history = ActivityLog::with('user')
            ->where('subject_type', $applicant->getMorphClass())
            ->where('subject_id', $applicant->id)
            ->orderByDesc('id')
            ->get();

        return view('applicants.show', [
            'applicant' => $applicant,
            'history'   => $history,
            'canEdit'   => ! $applicant->trashed() && $applicant->academicYear->is_active,
            'enrollmentSummary' => $enrollmentSummary,
            'whatsappUrl' => $applicant->phone
                ? app(WhatsAppMessageTemplate::class)->urlFor($applicant, '62'.substr($applicant->phone, 1))
                : null,
        ]);
    }

    public function edit(Applicant $applicant): View
    {
        $this->ensureEditable($applicant);

        return view('applicants.form', [
            'applicant' => $applicant,
            'year'      => $applicant->academicYear,
            'hint'      => null,
            'schools'   => $this->schoolOptions($applicant->school_id),
        ]);
    }

    public function update(ApplicantRequest $request, Applicant $applicant): RedirectResponse
    {
        $this->ensureEditable($applicant);
        $payload = $request->payload();

        $normalizeReferrer = fn (?string $value) => $value === null ? null : trim(preg_replace('/\s+/u', ' ', $value));
        if ($applicant->referrerLocked()
            && ! $request->user()->isAdmin()
            && ($payload['referrer_name'] !== $normalizeReferrer($applicant->referrer_name)
                || $payload['referrer_class_name'] !== $normalizeReferrer($applicant->referrer_class_name))) {
            return back()->withInput()->withErrors(['referrer_name' => 'Data pembawa sudah terkunci. Hubungi admin untuk mengubahnya.']);
        }

        if ($payload['person_key'] !== $applicant->person_key) {
            $warnings = $this->checks->warnings($applicant->academic_year_id, $payload['registration_seq'], $payload['person_key'], $applicant->id, false);
            if ($warnings && ! $request->boolean('confirm_warnings')) {
                return back()->withInput()->with('warnings', $warnings);
            }
        }

        $applicant->fill($payload);
        [$old, $new] = ActivityLog::changes($applicant, ['person_key', 'updated_at', 'registration_seq']);
        $applicant->save();

        if ($new) {
            ActivityLog::record('applicant.updated', "Data pendaftar {$applicant->registration_number} diubah", $applicant, $old, $new);
        }

        return redirect()->route('applicants.show', $applicant)->with('success', 'Data pendaftar diperbarui.');
    }

    /** Hanya admin (lihat route). Data tidak dihapus permanen. */
    public function destroy(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], [], ['reason' => 'alasan']);

        if ($applicant->payments()->whereNull('cancelled_at')->exists()) {
            return back()->with('error', 'Pendaftar ini sudah memiliki pembayaran sah dan tidak bisa dihapus. Batalkan pembayarannya lebih dulu.');
        }

        if ($applicant->uniformHandovers()->exists()) {
            return back()->with('error', 'Pendaftar ini sudah menerima seragam dan tidak bisa dihapus.');
        }

        $applicant->delete();

        ActivityLog::record(
            'applicant.deleted',
            "Pendaftar {$applicant->registration_number} {$applicant->name} dihapus",
            $applicant,
            null,
            null,
            $data['reason'],
        );

        return redirect()->route('applicants.index')->with('success', "Pendaftar {$applicant->registration_number} dihapus.");
    }

    public function restore(Request $request, Applicant $applicant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], [], ['reason' => 'alasan']);

        $applicant->restore();

        ActivityLog::record(
            'applicant.restored',
            "Pendaftar {$applicant->registration_number} {$applicant->name} dipulihkan",
            $applicant,
            null,
            null,
            $data['reason'],
        );

        return redirect()->route('applicants.show', $applicant)->with('success', 'Data pendaftar dipulihkan.');
    }

    /** Bukti pendaftaran: halaman siap cetak (bisa disimpan sebagai PDF dari dialog cetak browser). */
    public function print(Request $request, Applicant $applicant): View
    {
        abort_if($applicant->trashed() && ! $request->user()->isAdmin(), 404);

        $applicant->load(['school', 'academicYear', 'creator']);

        return view('applicants.print', ['applicant' => $applicant]);
    }

    private function ensureEditable(Applicant $applicant): void
    {
        abort_unless($applicant->academicYear()->value('is_active'), 403, 'Data tahun ajaran arsip hanya bisa dilihat.');
    }

    /** Sekolah aktif, ditambah sekolah milik pendaftar bila sudah dinonaktifkan. */
    private function schoolOptions(?int $currentId)
    {
        return School::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($w) => $w->orWhere('id', $currentId)))
            ->orderBy('name')
            ->get(['id', 'name', 'regency_name']);
    }
}
