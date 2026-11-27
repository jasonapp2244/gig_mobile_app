<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Auth routes are rate limited per IP + email, never per IP alone. */
class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_eleventh_login_in_a_minute_gets_429_json(): void
    {
        $body = ['email' => 'tauseefchoohan0401@gmail.com', 'password' => 'wrong-password'];

        for ($i = 0; $i < 10; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/auth/login', $body)->status());
        }

        $this->postJson('/api/auth/login', $body)
            ->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Too many attempts. Please try again in a minute.');
    }

    public function test_other_emails_on_the_same_ip_are_not_blocked(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'tauseefchoohan0401@gmail.com', 'password' => 'x']);
        }

        $this->assertNotSame(429, $this->postJson('/api/auth/login', ['email' => 'another.user@example.com', 'password' => 'x'])->status());
    }

    public function test_sixth_code_check_in_a_minute_gets_429(): void
    {
        $body = ['email' => 'tauseefchoohan0401@gmail.com', 'otp' => '000000'];

        for ($i = 0; $i < 5; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/auth/verify-otp', $body)->status());
        }

        $this->postJson('/api/auth/verify-otp', $body)->assertStatus(429);
    }

    public function test_guest_login_is_not_limited(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->postJson('/api/auth/guest-login')->assertOk();
        }
    }
}
