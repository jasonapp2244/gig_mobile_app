<?php

namespace Tests\Feature;

use App\Models\IncomeTrackerActivity;
use App\Models\TaskPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** GET /api/income-tracker/list and POST /api/income-tracker/update/{id} */
class IncomeTrackerApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->appUser();
        Sanctum::actingAs($this->user);
    }

    protected function appUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'user', 'status' => 'active'], $attributes));
    }

    protected function record(array $attributes = [], ?User $owner = null): TaskPayment
    {
        return TaskPayment::create(array_merge([
            'user_id'        => ($owner ?? $this->user)->id,
            'payment_title'  => 'Warehouse shift',
            'payment'        => 250,
            'paid_amount'    => 0,
            'note'           => 'Night shift',
            'create_date'    => '2026-11-01',
            'payment_status' => 'pending',
        ], $attributes));
    }

    protected function edit(TaskPayment $record, array $body)
    {
        return $this->postJson("/api/income-tracker/update/{$record->id}", $body);
    }

    // ---------------------------------------------------------------- edit

    public function test_edits_every_field(): void
    {
        $record = $this->record();

        $this->edit($record, [
            'payment_title'  => 'Delivery job',
            'payment'        => 300,
            'paid_amount'    => 100,
            'payment_status' => 'partial',
            'note'           => 'Paid part in cash',
            'date'           => '2026-11-10',
        ])
            ->assertOk()
            ->assertJson([
                'status'       => true,
                'message'      => 'Record updated successfully',
                'task_payment' => [
                    'id'             => $record->id,
                    'payment_title'  => 'Delivery job',
                    'payment'        => '300.00',
                    'paid_amount'    => '100.00',
                    'remaining'      => '200.00',
                    'payment_status' => 'partial',
                    'note'           => 'Paid part in cash',
                ],
            ])
            ->assertJsonCount(7, 'status_options')
            ->assertJsonPath('status_options.0', ['value' => 'pending', 'label' => 'Pending']);

        $this->assertStringStartsWith('2026-11-10', (string) $record->fresh()->create_date);
    }

    public function test_one_field_leaves_the_others_unchanged(): void
    {
        $record = $this->record(['payment_status' => 'owed']);

        $this->edit($record, ['note' => 'Call him Monday'])->assertOk();

        $fresh = $record->fresh();
        $this->assertSame('Call him Monday', $fresh->note);
        $this->assertSame('Warehouse shift', $fresh->payment_title);
        $this->assertSame('250.00', $fresh->payment);
        $this->assertSame('owed', $fresh->payment_status);
        $this->assertStringStartsWith('2026-11-01', (string) $fresh->create_date);
    }

    public function test_cannot_edit_another_users_record(): void
    {
        $record = $this->record([], $this->appUser());

        $this->edit($record, ['payment_title' => 'Hacked'])
            ->assertNotFound()
            ->assertJson(['status' => false, 'message' => 'Payment record not found']);

        $this->assertSame('Warehouse shift', $record->fresh()->payment_title);
    }

    public function test_guest_is_blocked(): void
    {
        $guest = $this->appUser(['is_guest' => true]);
        $record = $this->record([], $guest);
        Sanctum::actingAs($guest);

        $this->edit($record, ['note' => 'x'])->assertForbidden();
        $this->getJson('/api/income-tracker/list')->assertForbidden();
    }

    public function test_validation(): void
    {
        $record = $this->record();

        $this->edit($record, ['payment_status' => 'lost'])->assertStatus(422)->assertJsonValidationErrors('payment_status');
        $this->edit($record, ['payment' => 0])->assertStatus(422)->assertJsonValidationErrors('payment');
        $this->edit($record, ['payment_title' => ''])->assertStatus(422)->assertJsonValidationErrors('payment_title');
        $this->edit($record, ['date' => 'not a date'])->assertStatus(422)->assertJsonValidationErrors('date');

        // paid more than the total
        $this->edit($record, ['paid_amount' => 300])->assertStatus(422)->assertJsonValidationErrors('paid_amount');
        // lowering the total below what is already paid
        $partial = $this->record(['paid_amount' => 100, 'payment_status' => 'partial']);
        $this->edit($partial, ['payment' => 50])->assertStatus(422)->assertJsonValidationErrors('paid_amount');

        // partial needs 0 < paid < total
        $this->edit($record, ['payment_status' => 'partial'])->assertStatus(422)->assertJsonValidationErrors('paid_amount');
        $this->edit($record, ['payment_status' => 'partial', 'paid_amount' => 250])->assertStatus(422);

        $this->assertSame('pending', $record->fresh()->payment_status);
        $this->assertSame('0.00', $record->fresh()->paid_amount);
    }

    public function test_status_follows_amounts_when_not_chosen(): void
    {
        $record = $this->record();

        $this->edit($record, ['paid_amount' => 100])->assertJsonPath('task_payment.payment_status', 'partial');
        $this->edit($record, ['paid_amount' => 250])->assertJsonPath('task_payment.payment_status', 'paid');

        $partial = $this->record(['paid_amount' => 100, 'payment_status' => 'partial']);
        $this->edit($partial, ['paid_amount' => 0])->assertJsonPath('task_payment.payment_status', 'pending');

        // lowering the total to the paid amount settles it
        $partial2 = $this->record(['paid_amount' => 100, 'payment_status' => 'partial']);
        $this->edit($partial2, ['payment' => 100])->assertJsonPath('task_payment.payment_status', 'paid');

        // a status the user picked always wins
        $this->edit($this->record(), ['paid_amount' => 50, 'payment_status' => 'owed'])
            ->assertJsonPath('task_payment.payment_status', 'owed');

        // settled records keep their status when only the amount is fixed
        $this->edit($this->record(['payment_status' => 'received']), ['payment' => 400])
            ->assertJsonPath('task_payment.payment_status', 'received');
    }

    public function test_edit_is_logged_for_the_admin(): void
    {
        $record = $this->record();

        $this->edit($record, ['payment_status' => 'paid'])->assertOk();
        $this->edit($record, ['note' => 'done'])->assertOk();

        $this->assertDatabaseHas('income_tracker_activities', [
            'user_id' => $this->user->id, 'task_payment_id' => $record->id,
            'action' => IncomeTrackerActivity::ACTION_STATUS_CHANGED, 'old_status' => 'pending', 'new_status' => 'paid',
        ]);
        $this->assertDatabaseHas('income_tracker_activities', [
            'user_id' => $this->user->id, 'task_payment_id' => $record->id,
            'action' => IncomeTrackerActivity::ACTION_UPDATED,
        ]);
    }

    // ---------------------------------------------------------------- list

    public function test_list_is_sorted_by_entry_date_newest_first(): void
    {
        Carbon::setTestNow('2026-11-14 10:00:00');
        $this->record(['payment_title' => 'Oldest', 'create_date' => '2026-10-01']);
        $this->record(['payment_title' => 'Newest', 'create_date' => '2026-11-12']);
        // no date: uses the day it was added (today)
        $this->record(['payment_title' => 'No date', 'create_date' => null]);
        $this->record(['payment_title' => 'Middle', 'create_date' => '2026-11-05']);
        Carbon::setTestNow();

        $this->getJson('/api/income-tracker/list')
            ->assertOk()
            ->assertJson(['status' => true])
            ->assertJsonCount(7, 'status_options')
            ->assertJsonPath('data.data.*.payment_title', ['No date', 'Newest', 'Middle', 'Oldest']);
    }

    public function test_same_date_newest_added_first(): void
    {
        $first  = $this->record(['payment_title' => 'Added first']);
        $second = $this->record(['payment_title' => 'Added second']);

        $this->getJson('/api/income-tracker/list')
            ->assertJsonPath('data.data.*.id', [$second->id, $first->id]);
    }

    public function test_list_only_shows_own_records_and_filters_by_status(): void
    {
        $this->record(['payment_title' => 'Mine pending']);
        $this->record(['payment_title' => 'Mine paid', 'payment_status' => 'paid']);
        $this->record(['payment_title' => 'Someone else'], $this->appUser());

        $this->getJson('/api/income-tracker/list')->assertJsonPath('data.total', 2);

        $this->getJson('/api/income-tracker/list?status=paid')
            ->assertJsonPath('data.data.*.payment_title', ['Mine paid']);

        $this->getJson('/api/income-tracker/list?status=lost')->assertStatus(422);
    }

    public function test_list_pagination(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->record();
        }

        $this->getJson('/api/income-tracker/list')
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.last_page', 2)
            ->assertJsonCount(10, 'data.data');

        $this->getJson('/api/income-tracker/list?page=2')->assertJsonCount(2, 'data.data');
        $this->getJson('/api/income-tracker/list?per_page=1000')->assertJsonPath('data.per_page', 50);
    }

    public function test_empty_list(): void
    {
        $this->getJson('/api/income-tracker/list')
            ->assertOk()
            ->assertJson(['status' => false, 'message' => 'No records found', 'data' => []])
            ->assertJsonCount(7, 'status_options');
    }
}
