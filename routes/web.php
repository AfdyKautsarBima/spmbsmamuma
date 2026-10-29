<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\OccupationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ReferrerController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    // Panitia pendaftaran dan admin
    Route::middleware('role:admin,pendaftaran')->group(function () {
        Route::get('wilayah', [RegionController::class, 'children'])->name('regions.children');
        Route::get('referrers/search', [ReferrerController::class, 'search'])->name('referrers.search');
        Route::post('referrers', [ReferrerController::class, 'store'])->name('referrers.store');

        Route::get('applicants', [ApplicantController::class, 'index'])->name('applicants.index');
        Route::get('applicants/create', [ApplicantController::class, 'create'])->name('applicants.create');
        Route::post('applicants', [ApplicantController::class, 'store'])->name('applicants.store');
        Route::get('applicants/{applicant}', [ApplicantController::class, 'show'])->withTrashed()->name('applicants.show');
        Route::get('applicants/{applicant}/edit', [ApplicantController::class, 'edit'])->name('applicants.edit');
        Route::put('applicants/{applicant}', [ApplicantController::class, 'update'])->name('applicants.update');
        Route::get('berkas', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('berkas/{applicant}', [DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('berkas/{applicant}', [DocumentController::class, 'update'])->name('documents.update');

        Route::get('applicants/{applicant}/print', [ApplicantController::class, 'print'])->withTrashed()->name('applicants.print');
    });

    Route::middleware('role:admin')->group(function () {
        Route::delete('applicants/{applicant}', [ApplicantController::class, 'destroy'])->name('applicants.destroy');
        Route::patch('applicants/{applicant}/restore', [ApplicantController::class, 'restore'])->withTrashed()->name('applicants.restore');

        Route::resource('academic-years', AcademicYearController::class)->except(['show', 'destroy']);
        Route::patch('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])
            ->name('academic-years.activate');

        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        // Master data
        Route::get('master', [MasterController::class, 'index'])->name('master.index');
        Route::get('master/wilayah', [RegionController::class, 'index'])->name('regions.index');

        Route::resource('schools', SchoolController::class)->except(['show', 'destroy']);
        Route::patch('schools/{school}/toggle-active', [SchoolController::class, 'toggleActive'])
            ->name('schools.toggle-active');

        Route::resource('occupations', OccupationController::class)->except(['show', 'destroy']);
        Route::patch('occupations/{occupation}/toggle-active', [OccupationController::class, 'toggleActive'])
            ->name('occupations.toggle-active');

        Route::get('referrers', [ReferrerController::class, 'index'])->name('referrers.index');
        Route::put('referrers/{referrer}', [ReferrerController::class, 'update'])->name('referrers.update');
        Route::get('referrers/{referrer}/merge', [ReferrerController::class, 'mergeForm'])->name('referrers.merge.form');
        Route::post('referrers/{referrer}/merge', [ReferrerController::class, 'merge'])->name('referrers.merge');
    });
});
