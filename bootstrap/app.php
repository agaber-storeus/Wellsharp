<?php

use App\Http\Middleware\EnsureCurrentRole;
use App\Http\Middleware\EnsureSessionVersion;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequestCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/proctor.php'));
            Route::middleware('web')->group(base_path('routes/instructor.php'));
            Route::middleware('web')->group(base_path('routes/student.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [RequestCorrelationId::class]);
        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'current.role' => EnsureCurrentRole::class,
            'session.version' => EnsureSessionVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
