<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The task list endpoints recalculate status when they load. They may only move a task
 * forward (incomplete -> completed once it has ended), never back to incomplete.
 */
class TaskStatusOnReadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Employer $employer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'UTC'));

        $this->user = User::factory()->create(['timezone' => 'America/New_York']);
        $this->employer = Employer::create(['user_id' => $this->user->id, 'employer_name' => 'Acme']);
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function task(string $status, string $endUtc): Task
    {
        return Task::create([
            'user_id'            => $this->user->id,
            'employer_id'        => $this->employer->id,
            'employer'           => 'Acme',
            'task_date_time'     => Carbon::parse($endUtc, 'UTC')->subHours(8),
            'task_end_date_time' => Carbon::parse($endUtc, 'UTC'),
            'status'             => $status,
        ]);
    }

    public static function endpoints(): array
    {
        return [
            'tasks-status'        => ['tasks-status'],
            'show-employer-tasks' => ['show-employer-tasks'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_completed_task_with_future_end_stays_completed(string $endpoint): void
    {
        $doneEarly = $this->task('completed', '2026-11-13 20:00:00');

        $this->postJson("/api/{$endpoint}", ['employer_id' => $this->employer->id])->assertOk();

        $this->assertSame('completed', $doneEarly->fresh()->status);
    }

    #[DataProvider('endpoints')]
    public function test_ended_incomplete_task_becomes_completed(string $endpoint): void
    {
        $ended  = $this->task('incomplete', '2026-11-13 08:00:00');
        $future = $this->task('incomplete', '2026-11-13 20:00:00');

        $response = $this->postJson("/api/{$endpoint}", ['employer_id' => $this->employer->id])->assertOk();

        $this->assertSame('completed', $ended->fresh()->status);
        $this->assertSame('incomplete', $future->fresh()->status);

        $statuses = collect($response->json('tasks'))->pluck('status', 'id');
        $this->assertSame('completed', $statuses[$ended->id]);
        $this->assertSame('incomplete', $statuses[$future->id]);
    }

    public function test_employer_summary_counts_the_early_completed_task(): void
    {
        $this->task('completed', '2026-11-13 20:00:00');
        $this->task('incomplete', '2026-11-13 21:00:00');

        $this->postJson('/api/show-employer-tasks', ['employer_id' => $this->employer->id])
            ->assertOk()
            ->assertJsonPath('employee_summary.complete', 1)
            ->assertJsonPath('employee_summary.incomplete', 1);
    }
}
