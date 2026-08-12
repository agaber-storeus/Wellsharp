<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSessionVersion
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $expected = (int) $request->session()->get('auth.session_version', $user->session_version);

        if ($expected !== (int) $user->session_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['wellsharp_id' => 'Your session has expired. Please sign in again.']);
        }

        return $next($request);
    }
}
