<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAuthMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authMode = config('auth.auth_mode', 'both');

        // Block Google OAuth routes when manual_only mode
        if ($authMode === 'manual_only' && $request->is('auth/google/*')) {
            abort(404);
        }

        // Redirect to Google OAuth when google_only mode
        if ($authMode === 'google_only' && ($request->is('login') || $request->is('register'))) {
            return redirect()->route('auth.google.redirect');
        }

        return $next($request);
    }
}
