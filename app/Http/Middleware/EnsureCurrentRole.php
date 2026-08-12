<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCurrentRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user() && $request->user()->currentRole && in_array($request->user()->currentRole->key, $roles, true), 403);

        return $next($request);
    }
}
