<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Laravel\Sanctum\Sanctum;
use Lcobucci\JWT\UnencryptedToken;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/** GET /api/firebase-token gives the chat a Firebase custom token for the logged-in user. */
class FirebaseTokenEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_a_token_with_their_id_as_uid(): void
    {
        $user = User::factory()->create(['email' => 'tauseefchoohan0401@gmail.com', 'status' => 'active']);
        Sanctum::actingAs($user);

        $token = Mockery::mock(UnencryptedToken::class);
        $token->shouldReceive('toString')->andReturn('custom-token');
        $auth = Mockery::mock(FirebaseAuth::class);
        $auth->shouldReceive('createCustomToken')->once()->with((string) $user->id)->andReturn($token);
        $this->app->instance(FirebaseAuth::class, $auth);

        $this->getJson('/api/firebase-token')
            ->assertOk()
            ->assertJsonPath('uid', (string) $user->id)
            ->assertJsonPath('firebase_token', 'custom-token');
    }

    public function test_guest_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_guest' => true, 'status' => 'active']));

        $this->getJson('/api/firebase-token')->assertStatus(403)->assertJsonPath('action', 'require_login');
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/firebase-token')->assertStatus(401);
    }

    public function test_firebase_failure_returns_503_without_details(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']));

        $auth = Mockery::mock(FirebaseAuth::class);
        $auth->shouldReceive('createCustomToken')->andThrow(new RuntimeException('secret internal detail'));
        $this->app->instance(FirebaseAuth::class, $auth);

        $this->getJson('/api/firebase-token')
            ->assertStatus(503)
            ->assertJsonPath('status', false)
            ->assertDontSee('secret internal detail');
    }
}
