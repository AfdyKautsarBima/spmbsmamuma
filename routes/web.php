<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscountPeriodController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\OccupationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SeragamController;
use App\Http\Controllers\UniformSizeController;
use App\Http\Controllers\UniformTypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhatsAppTemplateController;
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

    // Laporan: izin per laporan diperiksa di ReportController
    Route::get('laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('laporan/{key}', [ReportController::class, 'show'])->where('key', '[a-z\-]+')->name('reports.show');

    // Panitia pendaftaran dan admin
    Route::middleware('role:admin,pendaftaran')->group(function () {
        Route::get('wilayah', [RegionController::class, 'children'])->name('regions.children');
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

    // Panitia daftar ulang dan admin
    Route::middleware('role:admin,daftar_ulang')->group(function () {
        Route::get('daftar-ulang', [EnrollmentController::class, 'index'])->name('enrollment.index');
        Route::get('daftar-ulang/{applicant}', [EnrollmentController::class, 'show'])->name('enrollment.show');
        Route::post('daftar-ulang/{applicant}/pembayaran', [EnrollmentController::class, 'storePayment'])->name('enrollment.payments.store');
        Route::post('daftar-ulang/{applicant}/mundur', [EnrollmentController::class, 'withdraw'])->name('enrollment.withdraw');
        Route::get('daftar-ulang/{applicant}/bukti', [EnrollmentController::class, 'proof'])->name('enrollment.proof');
        Route::get('kwitansi/{payment}', [EnrollmentController::class, 'receipt'])->name('enrollment.receipt');
    });

    // Panitia seragam dan admin
    Route::middleware('role:admin,seragam')->group(function () {
        Route::get('seragam', [SeragamController::class, 'index'])->name('seragam.index');
        Route::get('seragam/{applicant}', [SeragamController::class, 'show'])->name('seragam.show');
        Route::get('seragam/{applicant}/bukti', [SeragamController::class, 'proof'])->name('seragam.proof');
        Route::post('seragam/{applicant}/ukuran/{uniform_type}', [SeragamController::class, 'setSize'])->name('seragam.size');
        Route::post('seragam/{applicant}/serah/{uniform_type}', [SeragamController::class, 'handOver'])->name('seragam.handover');
        Route::post('seragam/{applicant}/batal/{uniform_type}', [SeragamController::class, 'cancelHandover'])->name('seragam.cancel');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('pengaturan/template-whatsapp', [WhatsAppTemplateController::class, 'edit'])->name('whatsapp-template.edit');
        Route::put('pengaturan/template-whatsapp', [WhatsAppTemplateController::class, 'update'])->name('whatsapp-template.update');

        Route::delete('applicants/{applicant}', [ApplicantController::class, 'destroy'])->name('applicants.destroy');
        Route::patch('applicants/{applicant}/restore', [ApplicantController::class, 'restore'])->withTrashed()->name('applicants.restore');

        Route::resource('academic-years', AcademicYearController::class)->except(['show', 'destroy']);
        Route::patch('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])
            ->name('academic-years.activate');

        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
            ->name('users.toggle-active');

        // Pembatalan, diskon, dan catatan pengembalian: admin saja
        Route::post('pembayaran/{payment}/batal', [EnrollmentController::class, 'cancelPayment'])->name('enrollment.payments.cancel');
        Route::post('daftar-ulang/{applicant}/batal-mundur', [EnrollmentController::class, 'cancelWithdrawal'])->name('enrollment.withdrawal.cancel');
        Route::post('daftar-ulang/{applicant}/diskon', [EnrollmentController::class, 'setDiscount'])->name('enrollment.discount');
        Route::put('daftar-ulang/{applicant}/catatan-pengembalian', [EnrollmentController::class, 'refundNote'])->name('enrollment.refund-note');

        Route::resource('discount-periods', DiscountPeriodController::class)->except(['show', 'destroy']);
        Route::patch('discount-periods/{discount_period}/toggle-active', [DiscountPeriodController::class, 'toggleActive'])
            ->name('discount-periods.toggle-active');

        // Master seragam
        Route::post('uniform-types/copy', [UniformTypeController::class, 'copy'])->name('uniform-types.copy');
        Route::resource('uniform-types', UniformTypeController::class)->except(['show', 'destroy']);
        Route::patch('uniform-types/{uniform_type}/toggle-active', [UniformTypeController::class, 'toggleActive'])->name('uniform-types.toggle-active');
        Route::resource('uniform-sizes', UniformSizeController::class)->except(['show', 'destroy']);
        Route::patch('uniform-sizes/{uniform_size}/toggle-active', [UniformSizeController::class, 'toggleActive'])->name('uniform-sizes.toggle-active');

        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        // Master data
        Route::get('master', [MasterController::class, 'index'])->name('master.index');
        Route::get('master/wilayah', [RegionController::class, 'index'])->name('regions.index');

        Route::get('schools/import-template', [SchoolController::class, 'importTemplate'])->name('schools.import-template');
        Route::post('schools/import', [SchoolController::class, 'import'])->name('schools.import');
        Route::resource('schools', SchoolController::class)->except(['show', 'destroy']);
        Route::patch('schools/{school}/toggle-active', [SchoolController::class, 'toggleActive'])
            ->name('schools.toggle-active');

        Route::resource('occupations', OccupationController::class)->except(['show', 'destroy']);
        Route::patch('occupations/{occupation}/toggle-active', [OccupationController::class, 'toggleActive'])
            ->name('occupations.toggle-active');

    });
});
