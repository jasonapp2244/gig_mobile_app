<?php

namespace Tests\Feature;

use App\Models\ListCategory;
use App\Models\ListStory;
use App\Models\listImage;
use App\Models\Task;
use App\Models\User;
use App\Services\ListImageThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Optional /tasks date range + paging, and marketplace thumbnails. */
class FastLoadingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user', 'status' => 'active', 'timezone' => 'UTC']);
        Sanctum::actingAs($this->user);
    }

    protected function task(string $start, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'user_id'            => $this->user->id,
            'employer'           => 'Acme',
            'task_date_time'     => $start,
            'task_end_date_time' => date('Y-m-d H:i:s', strtotime($start) + 8 * 3600),
            'pay'                => 100,
            'status'             => 'completed',
        ], $attributes));
    }

    // ------------------------------------------------------- /tasks

    public function test_tasks_without_parameters_is_unchanged(): void
    {
        $this->task('2026-10-01 09:00:00');
        $this->task('2026-11-01 09:00:00');

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('total_tasks', 2)
            ->assertJsonCount(2, 'tasks')
            ->assertJsonMissingPath('pagination')
            ->assertJsonPath('tasks.0.task_date_time', '2026-11-01 09:00:00');
    }

    public function test_tasks_date_range_uses_users_local_days(): void
    {
        $this->user->update(['timezone' => 'Asia/Dubai']); // UTC+4
        $this->task('2026-10-31 21:00:00', ['employer' => 'Nov 1 local']); // 01:00 on Nov 1 in Dubai
        $this->task('2026-10-31 19:00:00', ['employer' => 'Oct 31 local']); // 23:00 on Oct 31 in Dubai
        $this->task('2026-12-01 09:00:00', ['employer' => 'December']);

        $this->getJson('/api/tasks?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('total_tasks', 1)
            ->assertJsonPath('tasks.0.employer', 'Nov 1 local');
    }

    public function test_tasks_paging(): void
    {
        for ($d = 1; $d <= 5; $d++) {
            $this->task("2026-11-0{$d} 09:00:00");
        }

        $this->getJson('/api/tasks?per_page=2')
            ->assertJsonCount(2, 'tasks')
            ->assertJsonPath('total_tasks', 5)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 3)
            ->assertJsonPath('pagination.has_more', true)
            ->assertJsonPath('tasks.0.task_date_time', '2026-11-05 09:00:00');

        $this->getJson('/api/tasks?per_page=2&page=3')
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('pagination.has_more', false);
    }

    public function test_tasks_bad_parameters(): void
    {
        $this->getJson('/api/tasks?from=11-01-2026')->assertStatus(422);
        $this->getJson('/api/tasks?from=2026-11-10&to=2026-11-01')->assertStatus(422);
        $this->getJson('/api/tasks?per_page=500')->assertStatus(422);
    }

    // ------------------------------------------------------- thumbnails

    protected function story(): ListStory
    {
        $category = ListCategory::create(['category' => 'Tools', 'status' => 'active']);

        return ListStory::create([
            'user_id' => $this->user->id, 'category_id' => $category->id, 'title' => 'Drill',
            'location' => 'Dallas', 'condition' => 'used',
        ]);
    }

    public function test_uploaded_photo_gets_a_small_thumbnail_and_original_is_kept(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('drill.jpg', 2000, 1500)->store('list_images', 'public');
        $originalSize = Storage::disk('public')->size($path);

        $image = listImage::create(['list_id' => $this->story()->id, 'image_name' => 'drill.jpg', 'path' => $path, 'hash_name' => 'x']);

        $thumb = ListImageThumbnail::pathFor($path);
        Storage::disk('public')->assertExists($thumb);
        [$w, $h] = getimagesize(Storage::disk('public')->path($thumb));
        $this->assertSame([480, 360], [$w, $h]);
        $this->assertSame($originalSize, Storage::disk('public')->size($path));

        $json = $image->fresh()->toArray();
        $this->assertStringEndsWith($thumb, $json['thumb_url']);
        $this->assertStringEndsWith($path, $json['image_url']);

        $image->delete();
        Storage::disk('public')->assertMissing($thumb);
    }

    public function test_thumb_url_falls_back_to_full_image_and_command_backfills(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('old.png', 900, 900)->store('list_images', 'public');

        // an "old" photo saved before thumbnails existed (no model events)
        listImage::withoutEvents(fn () => listImage::create([
            'list_id' => $this->story()->id, 'image_name' => 'old.png', 'path' => $path, 'hash_name' => 'y',
        ]));
        $image = listImage::first();

        $this->assertSame($image->image_url, $image->thumb_url);

        $this->artisan('list-images:thumbnails')
            ->expectsOutput('Thumbnails created: 1, already there: 0, could not read: 0')
            ->assertSuccessful();
        $this->artisan('list-images:thumbnails')
            ->expectsOutput('Thumbnails created: 0, already there: 1, could not read: 0');

        $this->assertNotSame($image->image_url, $image->fresh()->thumb_url);
    }

    public function test_low_memory_skips_thumbnail_instead_of_crashing(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('huge.jpg', 3000, 3000)->store('list_images', 'public');

        $old = ini_get('memory_limit');
        ini_set('memory_limit', (string) (memory_get_usage(true) + 30 * 1024 * 1024)); // ~30 MB headroom, photo needs ~45 MB
        try {
            $image = listImage::create(['list_id' => $this->story()->id, 'image_name' => 'huge.jpg', 'path' => $path, 'hash_name' => 'h']);
        } finally {
            ini_set('memory_limit', $old);
        }

        Storage::disk('public')->assertMissing(ListImageThumbnail::pathFor($path));
        $this->assertSame($image->image_url, $image->thumb_url);
    }

    public function test_app_add_list_upload_still_works_and_returns_thumbs(): void
    {
        Storage::fake('public');
        $category = ListCategory::create(['category' => 'Tools', 'status' => 'active']);

        $response = $this->post('/api/add-list', [
            'title'       => 'Ladder',
            'category_id' => $category->id,
            'location'    => 'Austin',
            'condition'   => 'used',
            'images'      => [
                UploadedFile::fake()->image('one.jpg', 1600, 1200),
                UploadedFile::fake()->image('two.png', 800, 1000),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('status', true)->assertJsonCount(2, 'data.images');

        foreach ($response->json('data.images') as $img) {
            Storage::disk('public')->assertExists($img['path']);
            Storage::disk('public')->assertExists(ListImageThumbnail::pathFor($img['path']));
            $this->assertStringContainsString('list_thumbs/', $img['thumb_url']);
        }
    }

    public function test_marketplace_list_returns_thumb_url_next_to_image_url(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('a.jpg', 1200, 800)->store('list_images', 'public');
        listImage::create(['list_id' => $this->story()->id, 'image_name' => 'a.jpg', 'path' => $path, 'hash_name' => 'z']);

        $item = $this->getJson('/api/get-list')->assertOk()->json('data.data.0.images.0');

        $this->assertArrayHasKey('image_url', $item);
        $this->assertArrayHasKey('thumb_url', $item);
        $this->assertStringContainsString('list_thumbs/', $item['thumb_url']);
    }
}
