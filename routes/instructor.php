<?php

use App\Http\Controllers\Instructor\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('instructor')->name('instructor.')->middleware(['auth', 'active.user', 'session.version', 'current.role:instructor', 'sync.due-classes'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
});
