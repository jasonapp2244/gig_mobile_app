<?php

namespace Tests\Feature;

use App\Models\IncomeTrackerActivity;
use App\Models\TaskPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomeTrackerActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function appUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role'   => 'user',
            'status' => 'active',
        ], $attributes));
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    protected function payment(User $user, array $attributes = []): TaskPayment
    {
        return TaskPayment::create(array_merge([
            'user_id'        => $user->id,
            'payment_title'  => 'Warehouse shift',
            'payment'        => 250,
            'paid_amount'    => 0,
            'payment_status' => 'pending',
        ], $attributes));
    }

    public function test_adding_an_income_entry_is_logged(): void
    {
        $user = $this->appUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/task-payment', [
            'payment_title'  => 'Delivery job',
            'payment'        => 120,
            'payment_status' => 'owed',
        ])->assertOk();

        $this->assertDatabaseHas('income_tracker_activities', [
            'user_id'       => $user->id,
            'action'        => 'created',
            'payment_title' => 'Delivery job',
            'amount'        => 120,
            'new_status'    => 'owed',
        ]);
    }

    public function test_partial_payment_is_logged_with_amount_and_new_status(): void
    {
        $user = $this->appUser();
        $payment = $this->payment($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/task-payment', ['id' => $payment->id, 'paid_amount' => 100])->assertOk();

        $activity = IncomeTrackerActivity::where('action', 'partial_payment')->firstOrFail();
        $this->assertSame($payment->id, $activity->task_payment_id);
        $this->assertSame('100.00', $activity->amount);
        $this->assertSame('pending', $activity->old_status);
        $this->assertSame('partial', $activity->new_status);
        $this->assertStringContainsString('Recorded partial payment $100.00', $activity->description);
    }

    public function test_status_change_is_logged_with_old_and_new_status(): void
    {
        $user = $this->appUser();
        $payment = $this->payment($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/task-payment', ['id' => $payment->id, 'payment_status' => 'paid'])->assertOk();

        $this->assertDatabaseHas('income_tracker_activities', [
            'task_payment_id' => $payment->id,
            'action'          => 'status_changed',
            'old_status'      => 'pending',
            'new_status'      => 'paid',
        ]);
    }

    public function test_deleted_entry_keeps_its_activity_snapshot(): void
    {
        $user = $this->appUser();
        $payment = $this->payment($user, ['payment_status' => 'owed']);
        Sanctum::actingAs($user);

        $this->postJson("/api/task-payment-delete/{$payment->id}")->assertOk();

        $this->assertDatabaseMissing('task_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('income_tracker_activities', [
            'task_payment_id' => $payment->id,
            'action'          => 'deleted',
            'payment_title'   => 'Warehouse shift',
            'old_status'      => 'owed',
        ]);
    }

    public function test_screen_views_are_throttled_per_user(): void
    {
        Cache::flush();
        $user = $this->appUser();
        Sanctum::actingAs($user);

        $this->getJson('/api/earningSummary')->assertOk();
        $this->getJson('/api/earningSummary')->assertOk();
        $this->getJson('/api/task-payment-history');

        $this->assertSame(1, IncomeTrackerActivity::where('user_id', $user->id)->where('action', 'viewed')->count());
        $this->assertSame('earning_summary', IncomeTrackerActivity::where('action', 'viewed')->value('screen'));
    }

    public function test_guest_users_are_not_tracked(): void
    {
        Cache::flush();
        $guest = $this->appUser(['is_guest' => true]);
        Sanctum::actingAs($guest);

        $this->getJson('/api/earningSummary')->assertOk();

        $this->assertSame(0, IncomeTrackerActivity::count());
    }

    public function test_earning_summary_counts_received_payments(): void
    {
        $user = $this->appUser();
        $this->payment($user, ['payment' => 50, 'payment_status' => 'received']);
        $this->payment($user, ['payment' => 30, 'payment_status' => 'paid']);
        Sanctum::actingAs($user);

        $this->getJson('/api/earningSummary')
            ->assertOk()
            ->assertJson(['available_earning' => 80, 'net_earning' => 80]);
    }

    public function test_admin_endpoints_return_income_tracker_data(): void
    {
        $user = $this->appUser(['name' => 'Ali Khan']);
        $payment = $this->payment($user);
        IncomeTrackerActivity::create([
            'user_id'         => $user->id,
            'task_payment_id' => $payment->id,
            'action'          => 'created',
            'payment_title'   => 'Warehouse shift',
            'amount'          => 250,
            'new_status'      => 'pending',
        ]);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/income-tracker/dashboard-data')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.active_today', 1)
            ->assertJsonPath('activities.0.user_name', 'Ali Khan')
            ->assertJsonCount(30, 'stats.trend.labels');

        $firstId = IncomeTrackerActivity::max('id');
        $this->actingAs($admin, 'admin')
            ->getJson('/admin/income-tracker/dashboard-data?after_id=' . $firstId)
            ->assertOk()
            ->assertJsonCount(0, 'activities');

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/income-tracker/users-data')
            ->assertOk()
            ->assertJsonPath('users.0.user_id', $user->id)
            ->assertJsonPath('users.0.pending_total', 250);

        $this->actingAs($admin, 'admin')
            ->getJson("/admin/income-tracker/{$user->id}/data?status=pending")
            ->assertOk()
            ->assertJsonPath('summary.pending', 250)
            ->assertJsonPath('entries.0.source', 'Manual')
            ->assertJsonCount(1, 'timeline');

        $this->actingAs($admin, 'admin')->get('/admin/income-tracker')->assertOk();
        $this->actingAs($admin, 'admin')->get("/admin/income-tracker/{$user->id}")->assertOk()->assertSee('Ali Khan');
    }

    public function test_settled_entries_show_as_fully_paid_in_admin(): void
    {
        $user = $this->appUser();
        $this->payment($user, ['payment' => 80, 'payment_status' => 'received']);

        $this->actingAs($this->admin(), 'admin')
            ->getJson("/admin/income-tracker/{$user->id}/data")
            ->assertOk()
            ->assertJsonPath('entries.0.paid', '80.00')
            ->assertJsonPath('entries.0.remaining', '0.00')
            ->assertJsonPath('summary.received', 80);
    }

    public function test_team_and_test_accounts_are_hidden_from_admin_stats(): void
    {
        $counted  = $this->appUser(['name' => 'Real Customer', 'phone_number' => '9255398047']); // US 925 area code
        $byTz     = $this->appUser(['timezone' => 'Asia/Karachi']);
        $byPhone  = $this->appUser(['phone_number' => '0324 4078112']);
        $byEmail  = $this->appUser(['email' => 'fazal2425abbas@gmail.com']);

        foreach ([$counted, $byTz, $byPhone, $byEmail] as $user) {
            IncomeTrackerActivity::create(['user_id' => $user->id, 'action' => 'viewed', 'screen' => 'earning_summary']);
        }
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/income-tracker/dashboard-data')
            ->assertOk()
            ->assertJsonPath('stats.active_today', 1)
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.user_id', $counted->id);

        $this->actingAs($admin, 'admin')
            ->getJson('/admin/income-tracker/users-data')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.user_id', $counted->id);

        // A hidden user's own page still shows their data.
        $this->actingAs($admin, 'admin')
            ->getJson("/admin/income-tracker/{$byTz->id}/data")
            ->assertOk()
            ->assertJsonCount(1, 'timeline');
    }

    public function test_admin_pages_require_admin_login(): void
    {
        $this->get('/admin/income-tracker')->assertRedirect(route('admin.login'));
        $this->getJson('/admin/income-tracker/dashboard-data')->assertStatus(401);
    }
}
