<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isActive()) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // JSON/API callers (e.g. the batch Class-passwords endpoint) expect
        // a 403 like any other authorization failure, not a redirect to an
        // HTML login page - mirrors how EnsureCurrentRole already responds
        // for JSON requests via abort().
        if ($request->expectsJson()) {
            abort(403, 'Your account is not active.');
        }

        return redirect()->route('login')->withErrors(['wellsharp_id' => 'Your account is not active.']);
    }
}
