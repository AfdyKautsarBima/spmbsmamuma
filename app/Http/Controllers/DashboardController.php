<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\School;
use App\Models\User;
use App\Services\ApplicantChecks;
use App\Services\EnrollmentStats;
use App\Services\UniformStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApplicantChecks $checks, EnrollmentStats $enrollmentStats, UniformStats $uniformStats): View
    {
        $user = $request->user();
        $year = AcademicYear::active();

        $roleCounts = $user->isAdmin()
            ? User::where('is_active', true)->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role')
            : collect();

        $stats = null;
        if ($year && in_array($user->role, [Role::Admin, Role::Pendaftaran], true)) {
            $base = Applicant::where('academic_year_id', $year->id);
            $last = $checks->lastSeq($year->id);
            $total = (clone $base)->count();
            $complete = (clone $base)->where('documents_complete', true)->count();

            $stats = [
                'total'      => $total,
                'today'      => (clone $base)->whereDate('created_at', today())->count(),
                'last'       => $last > 0 ? Applicant::formatNumber($last) : null,
                'complete'   => $complete,
                'incomplete' => $total - $complete,
            ];
        }

        $enrollment = ($year && in_array($user->role, [Role::Admin, Role::DaftarUlang], true))
            ? $enrollmentStats->forYear($year)
            : null;

        $uniform = ($year && in_array($user->role, [Role::Admin, Role::Seragam], true))
            ? $uniformStats->forYear($year)
            : null;

        $charts = null;
        if ($year && $user->isAdmin()) {
            $enrollmentCounts = $enrollment['counts'];
            $charts = [
                'status' => [
                    ['label' => 'Pendaftar', 'count' => $enrollmentCounts['terdaftar'], 'color' => '#64748b'],
                    ['label' => 'Daftar ulang', 'count' => $enrollmentCounts['menyicil'] + $enrollmentCounts['lunas'], 'color' => '#16a34a'],
                    ['label' => 'Cabut', 'count' => $enrollmentCounts['mundur'], 'color' => '#dc2626'],
                ],
                'schools' => Applicant::query()
                    ->where('applicants.academic_year_id', $year->id)
                    ->join('schools', 'schools.id', '=', 'applicants.school_id')
                    ->select('schools.name', DB::raw('count(*) as total'))
                    ->groupBy('schools.id', 'schools.name')
                    ->orderByDesc('total')
                    ->orderBy('schools.name')
                    ->get()
                    ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->total])
                    ->all(),
            ];
        }

        return view('dashboard', [
            'year' => $year, 'roleCounts' => $roleCounts, 'stats' => $stats, 'enrollment' => $enrollment,
            'uniform' => $uniform, 'charts' => $charts,
        ]);
    }
}
