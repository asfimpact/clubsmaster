<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Only update if it hasn't been updated in the last 1 minute to save DB performance
            if (is_null(Auth::user()->last_activity_at) || Auth::user()->last_activity_at->diffInMinutes(now()) >= 1) {
                Auth::user()->update([
                    'last_activity_at' => now()
                ]);
            }
        }

        return $next($request);
    }
}
