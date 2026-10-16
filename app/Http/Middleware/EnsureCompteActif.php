<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompteActif
{
    // Un compte désactivé par le gérant est déconnecté à sa prochaine action
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Ce compte a été désactivé.']);
        }

        return $next($request);
    }
}
