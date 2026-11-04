<?php

namespace App\Http\Controllers;

use App\Models\Occupation;
use App\Models\Region;
use App\Models\School;
use Illuminate\View\View;

class MasterController extends Controller
{
    public function index(): View
    {
        return view('master.index', [
            'schoolsActive' => School::active()->count(),
            'schoolsTotal'  => School::count(),
            'occupations'   => Occupation::active()->count(),
            'regions'       => Region::count(),
        ]);
    }
}
