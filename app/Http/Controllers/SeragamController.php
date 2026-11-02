<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\UniformSize;
use App\Models\UniformType;
use App\Services\UniformService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeragamController extends Controller
{
    private const HANDED_ACTIVE = '(select count(*) from uniform_handovers h join uniform_types t on t.id = h.uniform_type_id
        where h.applicant_id = applicants.id and t.is_active = 1)';

    public function __construct(private UniformService $uniform)
    {
    }

    public function index(Request $request): View
    {
        $year = AcademicYear::active();
        $typeCount = $year ? UniformType::where('academic_year_id', $year->id)->where('is_active', true)->count() : 0;
        $status = (string) $request->query('status', 'berhak');

        $query = Applicant::query()
            ->with(['school', 'billing', 'uniformEntitlement', 'payments' => fn ($q) => $q->whereNull('cancelled_at')])
            ->withCount(['uniformHandovers as handed_active' => fn ($q) => $q->whereHas('type', fn ($t) => $t->where('is_active', true))])
            ->when($year, fn ($q, $y) => $q->where('academic_year_id', $y->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('registration_number', 'like', "%{$s}%")));

        match ($status) {
            'semua'        => $query->whereNull('withdrawn_at'),
            'belum_berhak' => $query->whereNull('withdrawn_at')->whereDoesntHave('uniformEntitlement'),
            'belum_ambil'  => $query->whereNull('withdrawn_at')->whereHas('uniformEntitlement')->whereDoesntHave('uniformHandovers'),
            'sebagian'     => $query->whereNull('withdrawn_at')->whereHas('uniformEntitlement')->whereHas('uniformHandovers')
                ->whereRaw(self::HANDED_ACTIVE.' < ?', [max($typeCount, 1)]),
            'lengkap'      => $query->whereNull('withdrawn_at')->whereHas('uniformEntitlement')->whereRaw(self::HANDED_ACTIVE.' >= ?', [max($typeCount, 1)]),
            'mundur_ambil' => $query->whereNotNull('withdrawn_at')->whereHas('uniformHandovers'),
            default        => $query->whereNull('withdrawn_at')->whereHas('uniformEntitlement'),
        };

        $applicants = $query->orderByDesc('registration_seq')->paginate(25)->withQueryString();
        $states = $applicants->mapWithKeys(fn ($a) => [$a->id => $this->uniform->state($a)]);

        return view('seragam.index', [
            'applicants' => $applicants, 'states' => $states, 'year' => $year, 'typeCount' => $typeCount, 'status' => $status,
        ]);
    }

    public function show(Applicant $applicant): View
    {
        $applicant->load(['school', 'billing', 'payments', 'uniformEntitlement', 'uniformHandovers.type', 'uniformHandovers.size', 'uniformHandovers.handler', 'uniformSizes', 'academicYear']);

        $handovers = $applicant->uniformHandovers->keyBy('uniform_type_id');
        $types = UniformType::where('academic_year_id', $applicant->academic_year_id)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $handovers->keys()))
            ->ordered()->get();

        return view('seragam.show', [
            'applicant' => $applicant,
            'state'     => $this->uniform->state($applicant),
            'types'     => $types,
            'handovers' => $handovers,
            'chosen'    => $applicant->uniformSizes->keyBy('uniform_type_id'),
            'sizes'     => UniformSize::ordered()->get(),
            'canWrite'  => $applicant->academicYear->is_active,
        ]);
    }

    public function setSize(Request $request, Applicant $applicant, UniformType $uniformType): RedirectResponse
    {
        $data = $request->validate(['uniform_size_id' => ['required', 'exists:uniform_sizes,id']], [], ['uniform_size_id' => 'ukuran']);

        return $this->attempt($applicant, fn () => $this->uniform->setSize(
            $applicant, $uniformType, UniformSize::findOrFail($data['uniform_size_id']), $request->user(),
        ), "Ukuran {$uniformType->name} disimpan.");
    }

    public function handOver(Request $request, Applicant $applicant, UniformType $uniformType): RedirectResponse
    {
        return $this->attempt($applicant, fn () => $this->uniform->handOver($applicant, $uniformType, $request->user()),
            "{$uniformType->name} diserahkan.");
    }

    public function cancelHandover(Request $request, Applicant $applicant, UniformType $uniformType): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']], [], ['reason' => 'alasan']);

        return $this->attempt($applicant, fn () => $this->uniform->cancelHandover($applicant, $uniformType, $data['reason'] ?? null, $request->user()),
            "Penyerahan {$uniformType->name} dibatalkan.");
    }

    public function proof(Applicant $applicant): View
    {
        $applicant->load(['school', 'academicYear', 'uniformHandovers.type', 'uniformHandovers.size', 'uniformHandovers.handler']);

        return view('seragam.proof', [
            'applicant' => $applicant,
            'types'     => UniformType::where('academic_year_id', $applicant->academic_year_id)->active()->ordered()->get(),
            'handovers' => $applicant->uniformHandovers->keyBy('uniform_type_id'),
        ]);
    }

    private function attempt(Applicant $applicant, callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return redirect()->route('seragam.show', $applicant)->with('error', $e->getMessage());
        }

        return redirect()->route('seragam.show', $applicant)->with('success', $success);
    }
}
