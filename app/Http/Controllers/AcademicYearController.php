<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicYearRequest;
use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Models\ActivityLog;
use App\Services\UniformService;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        return view('academic-years.index', ['years' => AcademicYear::orderByDesc('name')->get()]);
    }

    /** Form tahun baru: pengaturan disalin dari tahun aktif, nama disarankan tahun berikutnya. */
    public function create(): View
    {
        $base = AcademicYear::active() ?? AcademicYear::orderByDesc('name')->first();

        $suggested = '';
        if ($base && preg_match('/^(\d{4})\//', $base->name, $m)) {
            $suggested = ((int) $m[1] + 1).'/'.((int) $m[1] + 2);
        }

        return view('academic-years.form', ['year' => new AcademicYear([
            'name'                      => $suggested,
            'registration_fee'          => $base?->registration_fee ?? 0,
            'uniform_threshold_amount' => $base?->uniform_threshold_amount ?? 0,
        ]), 'copiedFrom' => $base?->name]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        // Tahun ajaran pertama otomatis aktif. Berikutnya harus diaktifkan manual.
        $year = AcademicYear::create($request->validated() + ['is_active' => ! AcademicYear::exists()]);

        ActivityLog::record('academic_year.created', "Tahun ajaran {$year->name} dibuat", $year, null, [
            'name'                      => $year->name,
            'registration_fee'          => $year->registration_fee,
            'uniform_threshold_amount' => $year->uniform_threshold_amount,
        ]);

        return redirect()->route('academic-years.index')->with('success', "Tahun ajaran {$year->name} dibuat.");
    }

    public function edit(AcademicYear $academicYear): View
    {
        return view('academic-years.form', ['year' => $academicYear, 'copiedFrom' => null]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->fill($request->validated());
        [$old, $new] = ActivityLog::changes($academicYear);
        $academicYear->save();

        if ($new) {
            ActivityLog::record('academic_year.updated', "Tahun ajaran {$academicYear->name} diubah", $academicYear, $old, $new);
        }

        $message = 'Tahun ajaran diperbarui.';

        // Ambang diturunkan: murid yang pembayarannya sudah cukup menjadi berhak. Hak yang ada tidak dicabut bila ambang dinaikkan.
        if (array_key_exists('uniform_threshold_amount', $new) && $academicYear->is_active) {
            $granted = app(UniformService::class)->syncYear($academicYear);
            $message .= " {$granted} murid mendapat hak seragam baru.";
        }

        return redirect()->route('academic-years.index')->with('success', $message);
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->is_active) {
            return back()->with('error', 'Tahun ajaran ini sudah aktif.');
        }

        $previous = AcademicYear::active();

        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $academicYear->update(['is_active' => true]);
        });

        ActivityLog::record(
            'academic_year.activated',
            "Tahun ajaran {$academicYear->name} diaktifkan",
            $academicYear,
            ['active' => $previous?->name],
            ['active' => $academicYear->name],
        );

        return back()->with('success', "Tahun ajaran {$academicYear->name} sekarang aktif.");
    }
}
