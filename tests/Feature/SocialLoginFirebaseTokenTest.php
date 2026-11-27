<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\UnencryptedToken;
use Mockery;
use Tests\TestCase;

/** POST /api/auth/social-login with and without a Firebase ID token. */
class SocialLoginFirebaseTokenTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'service_provider'    => 'google',
            'service_provider_id' => 'google-sub-123',
            'email'               => 'tauseefchoohan0401@gmail.com',
            'name'                => 'Test User',
            'timezone'            => 'UTC',
        ], $extra);
    }

    /** Make Firebase return a verified token with these claims. */
    private function fakeVerifiedToken(array $claims): void
    {
        $token = Mockery::mock(UnencryptedToken::class);
        $token->shouldReceive('claims')->andReturn(new DataSet($claims, ''));

        $auth = Mockery::mock(FirebaseAuth::class);
        $auth->shouldReceive('verifyIdToken')->andReturn($token);
        $this->app->instance(FirebaseAuth::class, $auth);
    }

    private function googleClaims(array $override = []): array
    {
        return array_merge([
            'sub'            => 'firebase-uid-1',
            'email'          => 'tauseefchoohan0401@gmail.com',
            'email_verified' => true,
            'firebase'       => [
                'sign_in_provider' => 'google.com',
                'identities'       => ['google.com' => ['google-sub-123'], 'email' => ['tauseefchoohan0401@gmail.com']],
            ],
        ], $override);
    }

    public function test_old_app_without_token_still_logs_in(): void
    {
        $this->postJson('/api/auth/social-login', $this->payload())
            ->assertOk()->assertJsonPath('status', true)->assertJsonStructure(['token']);
    }

    public function test_without_token_is_refused_once_required(): void
    {
        config(['services.social_login.require_firebase_token' => true]);

        $this->postJson('/api/auth/social-login', $this->payload())
            ->assertStatus(401)->assertJsonPath('status', false);
        $this->assertSame(0, User::count());
    }

    public function test_valid_token_logs_in(): void
    {
        $this->fakeVerifiedToken($this->googleClaims());

        $this->postJson('/api/auth/social-login', $this->payload(['firebase_id_token' => 'good']))
            ->assertOk()->assertJsonPath('user.email', 'tauseefchoohan0401@gmail.com');
    }

    public function test_firebase_uid_is_also_accepted_as_provider_id(): void
    {
        $this->fakeVerifiedToken($this->googleClaims());

        $this->postJson('/api/auth/social-login', $this->payload([
            'firebase_id_token'   => 'good',
            'service_provider_id' => 'firebase-uid-1',
        ]))->assertOk();
    }

    public function test_token_email_wins_over_a_forged_email(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'service_provider' => null, 'service_provider_id' => null]);
        $this->fakeVerifiedToken($this->googleClaims());

        $this->postJson('/api/auth/social-login', $this->payload([
            'firebase_id_token' => 'good',
            'email'             => 'victim@example.com',
        ]))->assertOk()->assertJsonPath('user.email', 'tauseefchoohan0401@gmail.com');

        $this->assertNull($victim->fresh()->service_provider_id, 'victim account must not be linked');
    }

    public function test_invalid_token_is_refused(): void
    {
        $auth = Mockery::mock(FirebaseAuth::class);
        $auth->shouldReceive('verifyIdToken')->andThrow(new FailedToVerifyToken('expired'));
        $this->app->instance(FirebaseAuth::class, $auth);

        $this->postJson('/api/auth/social-login', $this->payload(['firebase_id_token' => 'bad']))
            ->assertStatus(401)->assertJsonPath('status', false);
        $this->assertSame(0, User::count());
    }

    public function test_token_from_another_provider_is_refused(): void
    {
        $this->fakeVerifiedToken($this->googleClaims(['firebase' => ['sign_in_provider' => 'apple.com', 'identities' => []]]));

        $this->postJson('/api/auth/social-login', $this->payload(['firebase_id_token' => 'good']))
            ->assertStatus(401);
    }

    public function test_provider_id_not_in_token_is_refused(): void
    {
        $this->fakeVerifiedToken($this->googleClaims());

        $this->postJson('/api/auth/social-login', $this->payload([
            'firebase_id_token'   => 'good',
            'service_provider_id' => 'someone-elses-id',
        ]))->assertStatus(401);
    }
}
