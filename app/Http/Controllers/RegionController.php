<?php

namespace App\Http\Controllers;

use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionController extends Controller
{
    /** JSON untuk dropdown berjenjang. Tanpa parent = daftar provinsi. */
    public function children(Request $request): JsonResponse
    {
        $parent = $request->query('parent');

        if ($parent !== null && ! preg_match('/^\d{2}(\.\d{2}){0,2}$/', (string) $parent)) {
            return response()->json([]);
        }

        $items = Region::query()
            ->when($parent, fn ($q) => $q->where('parent_code', $parent), fn ($q) => $q->where('level', 1))
            ->get(['code', 'name']);

        $priority = array_flip(config('spmb.priority_regency_codes', []));
        $rank = fn (Region $r) => $priority[$r->code] ?? PHP_INT_MAX;

        $sorted = $items->sort(fn ($a, $b) => [$rank($a), $a->name] <=> [$rank($b), $b->name]);

        return response()->json($sorted->map(fn ($r) => ['code' => $r->code, 'name' => $r->name])->values());
    }

    /** Halaman admin: status data wilayah dan uji pemilih alamat. */
    public function index(): View
    {
        $counts = Region::query()->selectRaw('level, count(*) as total')->groupBy('level')->pluck('total', 'level');

        $configured = array_merge(
            [config('spmb.default_province_code')],
            config('spmb.priority_regency_codes', []),
        );

        return view('regions.index', [
            'counts' => $counts,
            'names'  => Region::whereIn('code', $configured)->pluck('name', 'code'),
        ]);
    }
}
