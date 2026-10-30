<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Applicant;
use App\Models\User;
use App\Services\ApplicantChecks;
use App\Services\EnrollmentStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ApplicantChecks $checks, EnrollmentStats $enrollmentStats): View
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

        return view('dashboard', [
            'year' => $year, 'roleCounts' => $roleCounts, 'stats' => $stats, 'enrollment' => $enrollment,
        ]);
    }
}
