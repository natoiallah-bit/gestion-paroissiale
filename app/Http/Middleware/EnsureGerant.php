<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGerant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->estGerant()) {
            abort(403, 'Page réservée au gérant.');
        }

        return $next($request);
    }
}
