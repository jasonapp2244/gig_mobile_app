<?php

namespace Tests\Feature;

use App\Models\Employer;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTaskStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function task(string $status, ?string $endUtc): Task
    {
        $user = User::factory()->create();
        $employer = Employer::create(['user_id' => $user->id, 'employer_name' => 'Acme']);

        return Task::create([
            'user_id'            => $user->id,
            'employer_id'        => $employer->id,
            'employer'           => 'Acme',
            'task_date_time'     => $endUtc ? Carbon::parse($endUtc, 'UTC')->subHours(8) : null,
            'task_end_date_time' => $endUtc ? Carbon::parse($endUtc, 'UTC') : null,
            'status'             => $status,
        ]);
    }

    public function test_ended_incomplete_tasks_become_completed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'UTC'));

        $ended   = $this->task('incomplete', '2026-11-13 08:00:00');
        $endsNow = $this->task('incomplete', '2026-11-13 12:00:00');

        $this->artisan('tasks:update-status')->assertSuccessful();

        // Must be the same value the API filters on ('completed'), not 'complete'
        $this->assertSame('completed', $ended->fresh()->status);
        $this->assertSame('completed', $endsNow->fresh()->status);
    }

    public function test_future_and_other_tasks_are_left_alone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'UTC'));

        $future    = $this->task('incomplete', '2026-11-13 20:00:00');
        $noEnd     = $this->task('incomplete', null);
        $cancelled = $this->task('cancelled', '2026-11-12 08:00:00');

        $this->artisan('tasks:update-status')->assertSuccessful();

        $this->assertSame('incomplete', $future->fresh()->status);
        $this->assertSame('incomplete', $noEnd->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_completed_tasks_show_up_in_the_employer_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'UTC'));

        $task = $this->task('incomplete', '2026-11-13 08:00:00');
        $this->artisan('tasks:update-status')->assertSuccessful();

        $this->assertSame(1, Task::where('employer_id', $task->employer_id)
            ->whereIn('status', ['incomplete', 'completed'])
            ->where('status', 'completed')
            ->count());
    }
}
