<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountEligible
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isEligible()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Your account is unavailable. Contact HR.'], 401)
                : redirect()->route('login')->withErrors(['email' => 'Your account is unavailable. Contact HR.']);
        }

        return $next($request);
    }
}
