<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** POST /api/update-profile saves the bio, and GET /api/user-profile returns it. */
class ProfileBioTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'email'  => 'tauseefchoohan0401@gmail.com',
            'role'   => 'user',
            'status' => 'active',
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_bio_is_saved_and_returned_by_profile(): void
    {
        $user = $this->user();

        $this->postJson('/api/update-profile', ['bio' => 'Stagehand and rigger, 10 years.'])
            ->assertOk()->assertJsonPath('status', true);

        $this->assertSame('Stagehand and rigger, 10 years.', $user->fresh()->bio);

        $this->getJson('/api/user-profile')
            ->assertOk()
            ->assertJsonPath('data.bio', 'Stagehand and rigger, 10 years.');
    }

    public function test_updating_other_fields_keeps_the_bio(): void
    {
        $user = $this->user(['bio' => 'Existing bio']);

        $this->postJson('/api/update-profile', ['city' => 'Austin'])->assertOk();

        $this->assertSame('Existing bio', $user->fresh()->bio);
        $this->assertSame('Austin', $user->fresh()->city);
    }

    public function test_bio_can_be_cleared(): void
    {
        $user = $this->user(['bio' => 'Old bio']);

        $this->postJson('/api/update-profile', ['bio' => ''])->assertOk();

        $this->assertNull($user->fresh()->bio);
    }

    public function test_bio_longer_than_1000_characters_is_rejected(): void
    {
        $user = $this->user(['bio' => 'Old bio']);

        // updateProfile() turns every validation error into its generic status:false reply (existing behaviour)
        $this->postJson('/api/update-profile', ['bio' => str_repeat('a', 1001)])
            ->assertJsonPath('status', false);

        $this->assertSame('Old bio', $user->fresh()->bio);
    }
}
