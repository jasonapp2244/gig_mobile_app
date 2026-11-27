<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate limits for the public auth API routes.
 *
 * Keyed by IP + email (not IP alone), so many phones behind one carrier/Wi-Fi
 * address never lock each other out; a lockout only hits the one email being tried.
 * Defined in a provider (not routes/api.php) so it still works after `php artisan optimize` caches the routes.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // login, social login, guest login, signup
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(10)
            ->by('auth-login|' . $this->key($request))
            ->response(fn () => $this->tooMany()));

        // OTP check / resend, forgot / reset password
        RateLimiter::for('auth-code', fn (Request $request) => Limit::perMinute(5)
            ->by('auth-code|' . $this->key($request))
            ->response(fn () => $this->tooMany()));
    }

    private function key(Request $request): string
    {
        $email = strtolower(trim((string) $request->input('email', '')));

        return $request->ip() . '|' . ($email !== '' ? $email : (string) $request->input('service_provider_id', ''));
    }

    private function tooMany()
    {
        return response()->json([
            'status'  => false,
            'message' => 'Too many attempts. Please try again in a minute.',
        ], 429);
    }
}
