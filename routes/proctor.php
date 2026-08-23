<?php

use App\Http\Controllers\Proctor\DashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('proctor')->name('proctor.')->middleware(['auth', 'active.user', 'session.version', 'current.role:proctor', 'sync.due-classes'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
});
