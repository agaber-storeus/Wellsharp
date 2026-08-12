<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if ($user) {
            LoginEvent::create([
                'user_id' => $user->getKey(), 'wellsharp_id' => $user->wellsharp_id, 'outcome' => 'logout',
                'correlation_id' => $request->attributes->get('correlation_id'), 'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(), 'occurred_at' => now(),
            ]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
