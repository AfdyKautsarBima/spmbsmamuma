<?php

namespace App\Http\Controllers;

use App\Http\Requests\OccupationRequest;
use App\Models\ActivityLog;
use App\Models\Occupation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OccupationController extends Controller
{
    public function index(): View
    {
        return view('occupations.index', ['occupations' => Occupation::ordered()->get()]);
    }

    public function create(): View
    {
        return view('occupations.form', ['occupation' => new Occupation(['sort_order' => 1000, 'is_active' => true])]);
    }

    public function store(OccupationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $occupation = Occupation::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 1000,
            'is_active'  => true,
        ]);

        ActivityLog::record('occupation.created', "Pekerjaan {$occupation->name} ditambahkan", $occupation);

        return redirect()->route('occupations.index')->with('success', "Pekerjaan {$occupation->name} ditambahkan.");
    }

    public function edit(Occupation $occupation): View
    {
        return view('occupations.form', ['occupation' => $occupation]);
    }

    public function update(OccupationRequest $request, Occupation $occupation): RedirectResponse
    {
        $data = $request->validated();
        $occupation->fill([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? $occupation->sort_order,
        ]);
        [$old, $new] = ActivityLog::changes($occupation);
        $occupation->save();

        if ($new) {
            ActivityLog::record('occupation.updated', "Pekerjaan {$occupation->name} diubah", $occupation, $old, $new);
        }

        return redirect()->route('occupations.index')->with('success', 'Pekerjaan diperbarui.');
    }

    public function toggleActive(Occupation $occupation): RedirectResponse
    {
        $occupation->update(['is_active' => ! $occupation->is_active]);

        ActivityLog::record(
            $occupation->is_active ? 'occupation.activated' : 'occupation.deactivated',
            "Pekerjaan {$occupation->name} ".($occupation->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            $occupation,
        );

        return back()->with('success', 'Status pekerjaan diperbarui.');
    }
}
