<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuthenticateUserAction $authenticate)
    {
        $key = 'login:'.strtolower($request->wellsharp_id).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return redirect()->route('login')->withErrors(['wellsharp_id' => 'Too many sign-in attempts. Please try again later.'])->withInput()->setStatusCode(429);
        }

        $user = $authenticate->execute(
            $request->wellsharp_id,
            $request->password,
            $request->ip(),
            $request->userAgent(),
            $request->attributes->get('correlation_id'),
        );

        if (! $user) {
            RateLimiter::hit($key, 60);

            return redirect()->route('login')->withErrors(['wellsharp_id' => 'The WellSharp ID or password is incorrect.'])->withInput();
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('auth.session_version', $user->session_version);

        $landingRoute = match ($user->currentRole?->key) {
            'admin' => 'admin.dashboard', 'proctor' => 'proctor.dashboard', 'instructor' => 'instructor.dashboard', 'student' => 'student.dashboard', default => 'home',
        };

        return redirect()->intended(route($landingRoute));
    }
}
