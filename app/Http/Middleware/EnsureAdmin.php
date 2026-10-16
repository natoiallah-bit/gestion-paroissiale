<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->estAdmin()) {
            abort(403, 'Page réservée à l\'administrateur.');
        }

        return $next($request);
    }
}
