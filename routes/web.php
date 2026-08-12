<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CertificateDocumentController;
use App\Http\Controllers\Operational\ExamControlController;
use App\Http\Controllers\Operational\NavigationController;
use App\Http\Controllers\Operational\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');

Route::get('/certificates/{certificate}', [CertificateDocumentController::class, 'show'])
    ->middleware(['auth', 'active.user', 'session.version'])
    ->name('certificates.show');

Route::get('/certificates/{certificate}/documents/{document}', [CertificateDocumentController::class, 'document'])
    ->middleware(['auth', 'active.user', 'session.version'])
    ->name('certificates.documents.show');

Route::get('/certificates/{certificate}/documents/{document}/download', [CertificateDocumentController::class, 'download'])
    ->middleware(['auth', 'active.user', 'session.version'])
    ->name('certificates.documents.download');

Route::get('/home', function () {
    $landingRoute = match (auth()->user()->currentRole?->key) {
        'admin' => 'admin.dashboard',
        'proctor' => 'proctor.dashboard',
        'instructor' => 'instructor.dashboard',
        'student' => 'student.dashboard',
        default => 'login',
    };

    return redirect()->route($landingRoute);
})->middleware(['auth', 'active.user', 'session.version'])->name('home');

foreach (['proctor', 'instructor'] as $operationalRole) {
    Route::prefix($operationalRole)->name($operationalRole.'.')->middleware(['auth', 'active.user', 'session.version', 'current.role:'.$operationalRole])->group(function (): void {
        Route::get('/profile', [NavigationController::class, 'profile'])->name('profile');
        Route::patch('/profile', [NavigationController::class, 'updateProfile'])->name('profile.update');
        Route::get('/analytics', [NavigationController::class, 'analytics'])->name('analytics');
        Route::get('/analytics/search', [NavigationController::class, 'analyticsSearch'])->name('analytics.search');
        Route::get('/analytics/results', [ReportController::class, 'results'])->name('analytics.results');
        Route::get('/analytics/attempts/{attempt}', [ReportController::class, 'show'])->name('analytics.attempts.show');
        Route::post('/analytics/attempts/{attempt}/release', [ReportController::class, 'release'])->name('analytics.attempts.release');
        Route::get('/classes', [NavigationController::class, 'classes'])->name('classes');
        Route::get('/browse', [NavigationController::class, 'browse'])->name('browse');
        Route::get('/browse/results', [NavigationController::class, 'browseResults'])->name('browse.results');
        Route::get('/certificate', [NavigationController::class, 'certificate'])->name('certificate');
        Route::get('/certificate/export', [NavigationController::class, 'certificateExport'])->name('certificate.export');
        Route::post('/proctor-id/verify', [ExamControlController::class, 'verifyProctorId'])->name('proctor-id.verify');
        Route::post('/classes/{trainingClass}/exam-control', [ExamControlController::class, 'control'])->name('classes.exam-control');
    });
}
