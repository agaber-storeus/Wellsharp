<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestCorrelationId
{
    public function handle(Request $request, Closure $next)
    {
        $id = (string) Str::uuid();
        $request->attributes->set('correlation_id', $id);
        Log::withContext(['correlation_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Correlation-ID', $id);

        return $response;
    }
}
