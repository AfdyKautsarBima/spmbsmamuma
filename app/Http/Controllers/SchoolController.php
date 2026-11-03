<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolRequest;
use App\Models\ActivityLog;
use App\Models\Region;
use App\Models\School;
use App\Reports\Report;
use App\Services\SchoolImportService;
use App\Support\XlsxWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use InvalidArgumentException;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $schools = School::query()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('npsn', 'like', "%{$s}%")
                ->orWhere('regency_name', 'like', "%{$s}%")))
            ->when($request->status === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('schools.index', ['schools' => $schools]);
    }

    public function create(): View
    {
        return view('schools.form', ['school' => new School(['is_active' => true])]);
    }

    public function store(SchoolRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['regency_name'] = Region::findOrFail($data['regency_code'])->name;

        $school = School::create($data + ['is_active' => true]);

        ActivityLog::record('school.created', "Sekolah {$school->name} ditambahkan", $school, null, [
            'name' => $school->name, 'npsn' => $school->npsn, 'regency_name' => $school->regency_name,
        ]);

        return redirect()->route('schools.index')->with('success', "Sekolah {$school->name} ditambahkan.");
    }

    public function import(Request $request, SchoolImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'extensions:xlsx'],
        ], [
            'file.required' => 'Pilih file Excel yang akan diimpor.',
            'file.extensions' => 'File harus berformat .xlsx.',
        ]);

        try {
            $count = $importer->import($request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return redirect()->route('schools.index')->with('success', "{$count} sekolah berhasil diimpor.");
    }

    public function importTemplate(): Response
    {
        $report = (new Report('Template Import Sekolah'))
            ->section(
                null,
                [
                    Report::col('Nama Sekolah'),
                    Report::col('NPSN'),
                    Report::col('Kode Kabupaten/Kota'),
                ],
                [],
            );

        return response(XlsxWriter::toString($report), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="template-import-sekolah.xlsx"',
        ]);
    }

    public function edit(School $school): View
    {
        return view('schools.form', ['school' => $school]);
    }

    public function update(SchoolRequest $request, School $school): RedirectResponse
    {
        $data = $request->validated();
        $data['regency_name'] = Region::findOrFail($data['regency_code'])->name;

        $school->fill($data);
        [$old, $new] = ActivityLog::changes($school);
        $school->save();

        if ($new) {
            ActivityLog::record('school.updated', "Sekolah {$school->name} diubah", $school, $old, $new);
        }

        return redirect()->route('schools.index')->with('success', 'Data sekolah diperbarui.');
    }

    public function toggleActive(School $school): RedirectResponse
    {
        $school->update(['is_active' => ! $school->is_active]);

        ActivityLog::record(
            $school->is_active ? 'school.activated' : 'school.deactivated',
            "Sekolah {$school->name} ".($school->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            $school,
        );

        return back()->with('success', 'Status sekolah diperbarui.');
    }
}
