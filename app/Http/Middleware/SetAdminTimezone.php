<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetAdminTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
      public function handle($request, Closure $next)
    {
        // Sirf admin user ka timezone set karo
        if (Auth::check() && Auth::user()->role_id == 1 && Auth::user()->timezone) {
            Config::set('app.timezone', Auth::user()->timezone); 
        }

        return $next($request);
    }
}
