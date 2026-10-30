<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\DiscountPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DiscountPeriodController extends Controller
{
    public function index(): View
    {
        return view('discount-periods.index', [
            'periods' => DiscountPeriod::with('academicYear')->orderByDesc('academic_year_id')->orderBy('starts_on')->get(),
            'year'    => AcademicYear::active(),
        ]);
    }

    public function create(): View
    {
        abort_if(! AcademicYear::active(), 403, 'Belum ada tahun ajaran aktif.');

        return view('discount-periods.form', ['period' => new DiscountPeriod(['percent' => 10])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AcademicYear::active();
        abort_if(! $year, 403, 'Belum ada tahun ajaran aktif.');

        $data = $this->validated($request, $year->id, null);
        $period = DiscountPeriod::create($data + ['academic_year_id' => $year->id, 'is_active' => true]);

        ActivityLog::record('discount_period.created', "Periode diskon {$period->name} ({$period->percent}%) dibuat", $period, null, [
            'name' => $period->name, 'percent' => $period->percent,
            'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString(),
        ]);

        return redirect()->route('discount-periods.index')->with('success', 'Periode diskon dibuat.');
    }

    public function edit(DiscountPeriod $discountPeriod): View
    {
        $this->ensureActiveYear($discountPeriod);

        return view('discount-periods.form', ['period' => $discountPeriod]);
    }

    public function update(Request $request, DiscountPeriod $discountPeriod): RedirectResponse
    {
        $this->ensureActiveYear($discountPeriod);

        $data = $this->validated($request, $discountPeriod->academic_year_id, $discountPeriod);
        $discountPeriod->fill($data);
        [$old, $new] = ActivityLog::changes($discountPeriod);
        $discountPeriod->save();

        if ($new) {
            ActivityLog::record('discount_period.updated', "Periode diskon {$discountPeriod->name} diubah (murid yang sudah ditandai tidak berubah)", $discountPeriod, $old, $new);
        }

        return redirect()->route('discount-periods.index')->with('success', 'Periode diskon diperbarui.');
    }

    public function toggleActive(DiscountPeriod $discountPeriod): RedirectResponse
    {
        $this->ensureActiveYear($discountPeriod);

        if (! $discountPeriod->is_active && $this->overlaps(
            $discountPeriod->academic_year_id, $discountPeriod->starts_on->toDateString(), $discountPeriod->ends_on->toDateString(), $discountPeriod->id,
        )) {
            return back()->with('error', 'Tidak bisa diaktifkan: tumpang tindih dengan periode diskon aktif lain.');
        }

        $discountPeriod->update(['is_active' => ! $discountPeriod->is_active]);

        ActivityLog::record(
            $discountPeriod->is_active ? 'discount_period.activated' : 'discount_period.deactivated',
            "Periode diskon {$discountPeriod->name} ".($discountPeriod->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            $discountPeriod,
        );

        return back()->with('success', 'Status periode diskon diperbarui.');
    }

    private function validated(Request $request, int $yearId, ?DiscountPeriod $period): array
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'starts_on'   => ['required', 'date_format:Y-m-d'],
            'ends_on'     => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'percent'     => ['required', 'integer', 'between:1,99'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], [
            'name' => 'nama periode', 'starts_on' => 'tanggal mulai', 'ends_on' => 'tanggal akhir',
            'percent' => 'persen diskon', 'description' => 'keterangan',
        ]);

        if (($period?->is_active ?? true) && $this->overlaps($yearId, $data['starts_on'], $data['ends_on'], $period?->id)) {
            throw ValidationException::withMessages(['starts_on' => 'Periode tumpang tindih dengan periode diskon aktif lain di tahun ajaran ini.']);
        }

        return $data;
    }

    private function overlaps(int $yearId, string $start, string $end, ?int $ignoreId): bool
    {
        return DiscountPeriod::query()
            ->where('academic_year_id', $yearId)
            ->where('is_active', true)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('starts_on', '<=', $end)
            ->whereDate('ends_on', '>=', $start)
            ->exists();
    }

    private function ensureActiveYear(DiscountPeriod $period): void
    {
        abort_unless($period->academicYear->is_active, 403, 'Periode diskon tahun ajaran arsip hanya bisa dilihat.');
    }
}
