<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\UniformSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UniformSizeController extends Controller
{
    public function index(): View
    {
        return view('uniform-sizes.index', ['sizes' => UniformSize::ordered()->get()]);
    }

    public function create(): View
    {
        return view('uniform-sizes.form', ['size' => new UniformSize(['sort_order' => 100])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $size = UniformSize::create($this->validated($request, null) + ['is_active' => true]);

        ActivityLog::record('uniform_size.created', "Ukuran seragam {$size->name} ditambahkan", $size);

        return redirect()->route('uniform-sizes.index')->with('success', "Ukuran {$size->name} ditambahkan.");
    }

    public function edit(UniformSize $uniformSize): View
    {
        return view('uniform-sizes.form', ['size' => $uniformSize]);
    }

    public function update(Request $request, UniformSize $uniformSize): RedirectResponse
    {
        $uniformSize->fill($this->validated($request, $uniformSize));
        [$old, $new] = ActivityLog::changes($uniformSize);
        $uniformSize->save();

        if ($new) {
            ActivityLog::record('uniform_size.updated', "Ukuran seragam {$uniformSize->name} diubah", $uniformSize, $old, $new);
        }

        return redirect()->route('uniform-sizes.index')->with('success', 'Ukuran diperbarui.');
    }

    public function toggleActive(UniformSize $uniformSize): RedirectResponse
    {
        $uniformSize->update(['is_active' => ! $uniformSize->is_active]);

        ActivityLog::record(
            $uniformSize->is_active ? 'uniform_size.activated' : 'uniform_size.deactivated',
            "Ukuran seragam {$uniformSize->name} ".($uniformSize->is_active ? 'diaktifkan' : 'dinonaktifkan'),
            $uniformSize,
        );

        return back()->with('success', 'Status ukuran diperbarui.');
    }

    private function validated(Request $request, ?UniformSize $size): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:20', Rule::unique('uniform_sizes', 'name')->ignore($size)],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
        ], [], ['name' => 'nama ukuran', 'sort_order' => 'urutan']);

        return ['name' => $data['name'], 'sort_order' => $data['sort_order'] ?? 100];
    }
}
