<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserActivity
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            // Online flag set karo (5 minutes ka expiry)
            Cache::put('user-is-online-' . Auth::id(), true, now()->addMinutes(5));
        }

        return $next($request);
    }
}
