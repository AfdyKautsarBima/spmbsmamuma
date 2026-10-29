<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Applicant;
use App\Models\Referrer;
use App\Services\ReferrerMatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReferrerController extends Controller
{
    public function __construct(private ReferrerMatcher $matcher)
    {
    }

    /** Halaman admin: daftar pembawa, tambah, ubah nama, gabung. */
    public function index(Request $request): View
    {
        $yearId = AcademicYear::active()?->id;

        $referrers = Referrer::query()
            ->withCount([
                'applicants as total_count',
                'applicants as year_count' => fn ($q) => $q->where('academic_year_id', $yearId),
            ])
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('referrers.index', ['referrers' => $referrers]);
    }

    /** Saran otomatis untuk kolom pembawa di form pendaftaran. */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json([]);
        }

        $key = $this->matcher->key($term);

        return response()->json(
            Referrer::query()
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('name_key', 'like', "%{$key}%"))
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name']),
        );
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate($this->rules(), [], ['name' => 'nama pembawa']);

        $name = Referrer::cleanName($request->input('name'));
        $key = $this->matcher->key($name);

        ['exact' => $exact, 'similar' => $similar] = $this->matcher->match($key, Referrer::all(['id', 'name', 'name_key']));

        if ($exact) {
            return $this->duplicate($request, $exact);
        }

        if ($similar->isNotEmpty() && ! $request->boolean('force')) {
            return $this->needsConfirmation($request, $similar, route('referrers.store'), 'POST', $name);
        }

        try {
            $referrer = Referrer::create(['name' => $name, 'name_key' => $key, 'created_by' => $request->user()->id]);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate($request, Referrer::where('name_key', $key)->first());
        }

        ActivityLog::record('referrer.created', "Pembawa {$referrer->name} ditambahkan", $referrer);

        return $request->expectsJson()
            ? response()->json(['id' => $referrer->id, 'name' => $referrer->name], 201)
            : redirect()->route('referrers.index')->with('success', "Pembawa {$referrer->name} ditambahkan.");
    }

    public function update(Request $request, Referrer $referrer): RedirectResponse
    {
        $request->validate($this->rules(), [], ['name' => 'nama pembawa']);

        $name = Referrer::cleanName($request->input('name'));
        $key = $this->matcher->key($name);

        ['exact' => $exact, 'similar' => $similar] = $this->matcher->match(
            $key, Referrer::all(['id', 'name', 'name_key']), $referrer->id,
        );

        if ($exact) {
            return $this->duplicate($request, $exact);
        }

        if ($similar->isNotEmpty() && ! $request->boolean('force')) {
            return $this->needsConfirmation($request, $similar, route('referrers.update', $referrer), 'PUT', $name);
        }

        $old = $referrer->name;
        $referrer->update(['name' => $name, 'name_key' => $key]);

        if ($old !== $name) {
            ActivityLog::record('referrer.renamed', "Pembawa {$old} diubah menjadi {$name}", $referrer, ['name' => $old], ['name' => $name]);
        }

        return redirect()->route('referrers.index')->with('success', 'Nama pembawa diperbarui.');
    }

    public function mergeForm(Referrer $referrer): View
    {
        return view('referrers.merge', [
            'referrer' => $referrer,
            'count'    => Applicant::withTrashed()->where('referrer_id', $referrer->id)->count(),
            'targets'  => Referrer::where('id', '!=', $referrer->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Gabungkan pembawa ganda: semua pendaftar dipindah ke pembawa tujuan, pembawa asal dihapus. */
    public function merge(Request $request, Referrer $referrer): RedirectResponse
    {
        $data = $request->validate(['target_id' => ['required', 'integer', 'exists:referrers,id']], [], ['target_id' => 'pembawa tujuan']);

        if ((int) $data['target_id'] === $referrer->id) {
            return back()->with('error', 'Pembawa tujuan tidak boleh sama dengan pembawa asal.');
        }

        $target = Referrer::findOrFail($data['target_id']);
        $moved = 0;

        DB::transaction(function () use ($referrer, $target, &$moved) {
            $ids = Applicant::withTrashed()->where('referrer_id', $referrer->id)->pluck('id');
            $moved = $ids->count();

            Applicant::withTrashed()->whereIn('id', $ids)->update(['referrer_id' => $target->id]);

            foreach (Applicant::withTrashed()->whereIn('id', $ids)->get() as $applicant) {
                ActivityLog::record(
                    'applicant.referrer_changed',
                    "Pembawa {$applicant->registration_number} diubah dari {$referrer->name} ke {$target->name}",
                    $applicant,
                    ['referrer' => $referrer->name],
                    ['referrer' => $target->name],
                    'Penggabungan pembawa ganda',
                );
            }

            ActivityLog::record(
                'referrer.merged',
                "Pembawa {$referrer->name} digabung ke {$target->name} ({$moved} pendaftar dipindahkan)",
                $target,
                ['merged' => $referrer->name],
                ['into' => $target->name],
            );

            $referrer->delete();
        });

        return redirect()->route('referrers.index')
            ->with('success', "Pembawa digabung ke {$target->name}. {$moved} pendaftar dipindahkan.");
    }

    private function rules(): array
    {
        return ['name' => ['required', 'string', 'min:2', 'max:100', 'regex:/\p{L}/u']];
    }

    private function duplicate(Request $request, Referrer $existing): JsonResponse|RedirectResponse
    {
        $message = "Pembawa \"{$existing->name}\" sudah ada.";

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'existing' => ['id' => $existing->id, 'name' => $existing->name]], 422)
            : back()->withInput()->with('error', $message);
    }

    private function needsConfirmation(Request $request, $similar, string $action, string $method, string $name): JsonResponse|RedirectResponse
    {
        $list = $similar->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all();

        if ($request->expectsJson()) {
            return response()->json(['requires_confirmation' => true, 'similar' => $list], 409);
        }

        return back()->withInput()->with('confirm', [
            'action' => $action, 'method' => $method, 'name' => $name, 'similar' => array_column($list, 'name'),
        ]);
    }
}
