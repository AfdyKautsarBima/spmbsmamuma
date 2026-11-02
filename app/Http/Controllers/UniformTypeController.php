<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\UniformType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UniformTypeController extends Controller
{
    public function index(): View
    {
        $active = AcademicYear::active();

        return view('uniform-types.index', [
            'year'     => $active,
            'types'    => UniformType::with('academicYear')->orderByDesc('academic_year_id')->ordered()->get(),
            'otherYears' => AcademicYear::where('is_active', false)->whereIn('id', UniformType::query()->select('academic_year_id'))->orderByDesc('name')->get(),
        ]);
    }

    public function create(): View
    {
        abort_if(! AcademicYear::active(), 403, 'Belum ada tahun ajaran aktif.');

        return view('uniform-types.form', ['type' => new UniformType(['sort_order' => 100, 'needs_size' => false])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AcademicYear::active();
        abort_if(! $year, 403, 'Belum ada tahun ajaran aktif.');

        $data = $this->validated($request, $year->id, null);
        $type = UniformType::create($data + ['academic_year_id' => $year->id, 'is_active' => true]);

        ActivityLog::record('uniform_type.created', "Jenis seragam {$type->name} ditambahkan", $type, null, [
            'name' => $type->name, 'needs_size' => $type->needs_size,
        ]);

        return redirect()->route('uniform-types.index')->with('success', "Jenis seragam {$type->name} ditambahkan.");
    }

    public function edit(UniformType $uniformType): View
    {
        $this->ensureActiveYear($uniformType);

        return view('uniform-types.form', ['type' => $uniformType]);
    }

    public function update(Request $request, UniformType $uniformType): RedirectResponse
    {
        $this->ensureActiveYear($uniformType);

        $uniformType->fill($this->validated($request, $uniformType->academic_year_id, $uniformType));
        [$old, $new] = ActivityLog::changes($uniformType);
        $uniformType->save();

        if ($new) {
            ActivityLog::record('uniform_type.updated', "Jenis seragam {$uniformType->name} diubah", $uniformType, $old, $new);
        }

        return redirect()->route('uniform-types.index')->with('success', 'Jenis seragam diperbarui.');
    }

    public function toggleActive(UniformType $uniformType): RedirectResponse
    {
        $this->ensureActiveYear($uniformType);

        $uniformType->update(['is_active' => ! $uniformType->is_active]);

        ActivityLog::record(
            $uniformType->is_active ? 'uniform_type.activated' : 'uniform_type.deactivated',
            "Jenis seragam {$uniformType->name} ".($uniformType->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            $uniformType,
        );

        return back()->with('success', 'Status jenis seragam diperbarui.');
    }

    /** Salin jenis seragam dari tahun ajaran lain (yang belum ada namanya) ke tahun ajaran aktif. */
    public function copy(Request $request): RedirectResponse
    {
        $year = AcademicYear::active();
        abort_if(! $year, 403, 'Belum ada tahun ajaran aktif.');

        $data = $request->validate(['from_year' => ['required', 'exists:academic_years,id']], [], ['from_year' => 'tahun ajaran sumber']);

        if ((int) $data['from_year'] === $year->id) {
            return back()->with('error', 'Pilih tahun ajaran lain sebagai sumber.');
        }

        $existing = UniformType::where('academic_year_id', $year->id)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $copied = 0;

        foreach (UniformType::where('academic_year_id', $data['from_year'])->ordered()->get() as $source) {
            if (in_array(mb_strtolower($source->name), $existing, true)) {
                continue;
            }
            UniformType::create([
                'academic_year_id' => $year->id, 'name' => $source->name, 'needs_size' => $source->needs_size,
                'sort_order' => $source->sort_order, 'is_active' => true,
            ]);
            $copied++;
        }

        ActivityLog::record('uniform_type.copied', "{$copied} jenis seragam disalin ke TA {$year->name}", $year);

        return back()->with('success', "{$copied} jenis seragam disalin.");
    }

    private function validated(Request $request, int $yearId, ?UniformType $type): array
    {
        $request->merge(['name' => trim(preg_replace('/\s+/u', ' ', (string) $request->input('name')))]);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100',
                Rule::unique('uniform_types', 'name')->where('academic_year_id', $yearId)->ignore($type)],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ], ['name.unique' => 'Jenis seragam dengan nama itu sudah ada di tahun ajaran ini.'], ['name' => 'nama seragam', 'sort_order' => 'urutan']);

        return [
            'name'       => $data['name'],
            'needs_size' => $request->boolean('needs_size'),
            'sort_order' => $data['sort_order'] ?? 100,
        ];
    }

    private function ensureActiveYear(UniformType $type): void
    {
        abort_unless($type->academicYear->is_active, 403, 'Jenis seragam tahun ajaran arsip hanya bisa dilihat.');
    }
}
