<?php

use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\DashboardController;
use App\Http\Controllers\Student\ExamAttemptController;
use App\Http\Controllers\Student\FlowController;
use Illuminate\Support\Facades\Route;

Route::prefix('student')->name('student.')->middleware(['auth', 'active.user', 'session.version', 'current.role:student'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates');
    Route::get('/schedules/{schedule}/confirm', [FlowController::class, 'confirm'])->name('confirm');
    Route::post('/schedules/{schedule}/confirm', [FlowController::class, 'confirmStore'])->name('confirm.store');
    Route::get('/schedules/{schedule}/survey', [FlowController::class, 'surveyStart'])->name('survey.start');
    Route::get('/schedules/{schedule}/survey/form', [FlowController::class, 'surveyForm'])->name('survey.form');
    Route::post('/schedules/{schedule}/survey', [FlowController::class, 'surveyStore'])->name('survey.store');
    Route::get('/schedules/{schedule}/proctor', [FlowController::class, 'proctor'])->name('proctor');
    Route::post('/schedules/{schedule}/start', [ExamAttemptController::class, 'start'])->name('exams.start');
    Route::get('/attempts/{attempt}', [ExamAttemptController::class, 'show'])->name('attempts.show');
    Route::post('/attempts/{attempt}/expire', [ExamAttemptController::class, 'expire'])->name('attempts.expire');
    Route::post('/attempts/{attempt}/submit', [ExamAttemptController::class, 'submit'])->name('attempts.submit');
    Route::get('/attempts/{attempt}/report', [ExamAttemptController::class, 'report'])->name('attempts.report');
    Route::patch('/attempts/{attempt}/questions/{attemptQuestion}/answer', [ExamAttemptController::class, 'saveAnswer'])->name('attempts.answers.update');
});
