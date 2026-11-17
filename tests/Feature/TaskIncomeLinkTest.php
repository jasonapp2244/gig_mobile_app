<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Tasks no longer create income; income records link to a task by choice. */
class TaskIncomeLinkTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->appUser();
        Sanctum::actingAs($this->user);
    }

    protected function appUser(): User
    {
        return User::factory()->create(['role' => 'user', 'status' => 'active']);
    }

    protected function task(array $attributes = [], ?User $owner = null): Task
    {
        return Task::create(array_merge([
            'user_id'            => ($owner ?? $this->user)->id,
            'employer'           => 'Acme Logistics',
            'position'           => 'Driver',
            'task_date_time'     => '2026-11-10 09:00:00',
            'task_end_date_time' => '2026-11-10 17:00:00',
            'st_hours'           => '8:00',
            'st_wages'           => 20,
            'st_total'           => 160,
            'pay'                => 160,
            'status'             => 'completed',
        ], $attributes));
    }

    protected function income(array $attributes = []): TaskPayment
    {
        return TaskPayment::create(array_merge([
            'user_id'        => $this->user->id,
            'payment_title'  => 'Acme Logistics',
            'payment'        => 160,
            'paid_amount'    => 0,
            'payment_status' => 'pending',
            'create_date'    => '2026-11-10',
        ], $attributes));
    }

    // ------------------------------------------------------- task create

    public function test_creating_a_task_with_hours_does_not_create_income(): void
    {
        $this->postJson('/api/tasks', [
            'employer'       => 'Acme Logistics',
            'task_date_time' => '2026-11-10 09:00:00',
            'end_time'       => '2026-11-10 17:00:00',
            'st_total_hours' => '8:00',
            'st_rate'        => 20,
        ])->assertOk()->assertJsonPath('status', true)->assertJsonPath('task.pay', '160.00');

        $this->assertSame(1, Task::count());
        $this->assertSame(0, TaskPayment::count());
    }

    public function test_creating_a_task_without_hours_does_not_create_income(): void
    {
        $this->postJson('/api/tasks', [
            'employer'       => 'Acme Logistics',
            'task_date_time' => '2026-11-11 09:00:00',
            'end_time'       => '2026-11-11 17:00:00',
        ])->assertOk();

        $this->assertSame(0, TaskPayment::count());
    }

    // ------------------------------------------------------- add income

    public function test_add_income_linked_to_a_task(): void
    {
        $task = $this->task();

        $this->postJson('/api/task-payment', [
            'payment_title'  => 'Acme Logistics',
            'payment'        => 160,
            'payment_status' => 'pending',
            'date'           => '2026-11-10',
            'task_id'        => $task->id,
        ])->assertOk()->assertJsonPath('task_payment.task_id', $task->id);

        $this->assertSame($task->id, (int) TaskPayment::first()->task_id);
    }

    public function test_add_income_without_task_works_as_before(): void
    {
        $this->postJson('/api/task-payment', [
            'payment_title' => 'Cash job',
            'payment'       => 50,
        ])->assertOk()->assertJsonPath('task_payment.payment_status', 'paid');

        $this->assertNull(TaskPayment::first()->task_id);
    }

    public function test_add_income_rejects_other_users_task_and_already_linked_task(): void
    {
        $othersTask = $this->task([], $this->appUser());
        $this->postJson('/api/task-payment', ['payment_title' => 'x', 'payment' => 10, 'task_id' => $othersTask->id])
            ->assertStatus(422)->assertJsonValidationErrors('task_id');

        $task = $this->task();
        $this->income(['task_id' => $task->id]);
        $this->postJson('/api/task-payment', ['payment_title' => 'x', 'payment' => 10, 'task_id' => $task->id])
            ->assertStatus(422)->assertJsonPath('message', 'This task already has an income record.');

        $this->assertSame(1, TaskPayment::count());
    }

    // ------------------------------------------------------- edit income

    public function test_edit_income_links_changes_and_unlinks_a_task(): void
    {
        $income = $this->income();
        $taskA  = $this->task();
        $taskB  = $this->task(['employer' => 'Beta Co']);

        $this->postJson("/api/income-tracker/update/{$income->id}", ['task_id' => $taskA->id])
            ->assertOk()->assertJsonPath('task_payment.task_id', $taskA->id);

        $this->postJson("/api/income-tracker/update/{$income->id}", ['task_id' => $taskB->id])
            ->assertOk()->assertJsonPath('task_payment.task_id', $taskB->id);

        // saving again with its own task is fine
        $this->postJson("/api/income-tracker/update/{$income->id}", ['task_id' => $taskB->id, 'note' => 'ok'])->assertOk();

        $this->postJson("/api/income-tracker/update/{$income->id}", ['task_id' => null])
            ->assertOk()->assertJsonPath('task_payment.task_id', null);
    }

    public function test_edit_income_rejects_bad_task(): void
    {
        $income = $this->income();
        $linked = $this->task();
        $this->income(['task_id' => $linked->id]);

        $this->postJson("/api/income-tracker/update/{$income->id}", ['task_id' => $linked->id])
            ->assertStatus(422)->assertJsonValidationErrors('task_id');

        $this->postJson("/api/income-tracker/update/{$income->id}", ['task_id' => $this->task([], $this->appUser())->id])
            ->assertStatus(422)->assertJsonPath('message', 'Task not found.');

        $this->assertNull($income->fresh()->task_id);
    }

    // ------------------------------------------------------- task edit sync (unchanged)

    public function test_editing_a_task_still_syncs_its_linked_income(): void
    {
        $task   = $this->task();
        $income = $this->income(['task_id' => $task->id, 'payment_title' => 'Old title']);

        $this->postJson("/api/tasks_update/{$task->id}", ['employer' => 'Acme New', 'ot_hours' => '1:00', 'ot_rate' => 30])
            ->assertOk();

        $fresh = $income->fresh();
        $this->assertSame('Acme New', $fresh->payment_title);
        $this->assertSame('190.00', number_format((float) $fresh->payment, 2, '.', ''));
    }

    // ------------------------------------------------------- task picker

    public function test_task_picker_lists_only_own_unlinked_tasks(): void
    {
        $free    = $this->task(['employer' => 'Free task']);
        $linked  = $this->task(['employer' => 'Linked task']);
        $this->income(['task_id' => $linked->id]);
        $this->task(['employer' => 'Someone else'], $this->appUser());

        $this->getJson('/api/income-tracker/tasks')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $free->id)
            ->assertJsonPath('data.data.0.employer', 'Free task')
            ->assertJsonPath('data.data.0.pay', '160.00');

        // the edit form also gets the task already linked to the record
        $this->getJson("/api/income-tracker/tasks?include_task_id={$linked->id}")
            ->assertJsonPath('data.total', 2);
    }

    public function test_task_picker_shows_task_time_in_users_timezone(): void
    {
        $this->user->update(['timezone' => 'Asia/Dubai']); // UTC+4, no DST
        $this->task(['task_date_time' => '2026-11-10 09:00:00']);

        $this->getJson('/api/income-tracker/tasks')
            ->assertJsonPath('data.data.0.task_date_time', '2026-11-10 13:00:00');
    }

    public function test_task_picker_empty(): void
    {
        $this->getJson('/api/income-tracker/tasks')
            ->assertOk()
            ->assertJson(['status' => false, 'message' => 'No tasks available to link', 'data' => []]);
    }
}
