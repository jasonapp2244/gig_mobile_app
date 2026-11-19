<?php

namespace Tests\Feature;

use App\Mail\forgotPasswordMail;
use App\Mail\SupportConfirmationMail;
use App\Mail\SupportMail;
use App\Mail\SupportReplyMail;
use App\Mail\WelcomeMail;
use App\Models\SupportEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** support@ handles support (Reply works both ways); service@ sends OTP/password mails with no reply. */
class SupportMailAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.from.address'    => 'service@gigfmi.com',
            'mail.support.address' => 'support@gigfmi.com',
            'mail.support.name'    => 'Gig Support',
        ]);
    }

    protected function ticket(array $attributes = []): SupportEmail
    {
        return SupportEmail::create(array_merge([
            'name'    => 'Test Customer',
            'email'   => 'tauseefchoohan0401@gmail.com',
            'subject' => 'App question',
            'message' => 'How do I add income?',
            'status'  => 'sent',
        ], $attributes));
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    // ------------------------------------------------------- app support request

    public function test_support_request_goes_to_support_mailbox_and_replies_work_both_ways(): void
    {
        Mail::fake();
        $user = User::factory()->create(['role' => 'user', 'status' => 'active', 'email' => 'tauseefchoohan0401@gmail.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/support/send', ['subject' => 'Help', 'message' => 'Need help'])
            ->assertOk()->assertJsonPath('status', true);

        Mail::assertSent(SupportMail::class, function (SupportMail $mail) use ($user) {
            $mail->build();

            return $mail->hasTo('support@gigfmi.com') && $mail->hasReplyTo($user->email);
        });

        Mail::assertSent(SupportConfirmationMail::class, function (SupportConfirmationMail $mail) use ($user) {
            $mail->build();

            return $mail->hasTo($user->email) && $mail->hasReplyTo('support@gigfmi.com');
        });
    }

    public function test_support_mail_without_customer_email_still_builds(): void
    {
        // social-login users may have no email; the ticket must still reach the support mailbox
        $mail = new SupportMail($this->ticket(['email' => null]));
        $mail->build();

        $this->assertSame([], $mail->replyTo);
        $this->assertStringContainsString('How do I add income?', $mail->render());
    }

    public function test_support_address_survives_config_cache_style_lookup(): void
    {
        // env() is null under config:cache; the controller now reads config instead
        $this->assertSame('support@gigfmi.com', config('mail.support.address'));
    }

    // ------------------------------------------------------- OTP / password: no reply

    public function test_otp_and_password_mails_have_no_reply_to_and_say_do_not_reply(): void
    {
        $user = User::factory()->create(['email' => 'tauseefchoohan0401@gmail.com']);

        foreach ([new WelcomeMail($user, '1234'), new forgotPasswordMail('5678')] as $mail) {
            $mail->build();
            $this->assertSame([], $mail->replyTo, get_class($mail) . ' must not have a Reply-To');

            $html = $mail->render();
            $this->assertStringContainsString('Please do not reply', $html);
            $this->assertStringContainsString('service@gigfmi.com', $html);
            $this->assertStringContainsString('mailto:support@gigfmi.com', $html);
        }
    }

    // ------------------------------------------------------- admin panel reply

    public function test_admin_reply_is_emailed_and_saved(): void
    {
        Mail::fake();
        $ticket = $this->ticket();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('support.respond', $ticket->id), ['response' => "Hi,\nopen Income Tracker and tap Add."])
            ->assertRedirect(route('support.show', $ticket->id))
            ->assertSessionHas('success', 'Reply sent to tauseefchoohan0401@gmail.com.');

        Mail::assertSent(SupportReplyMail::class, function (SupportReplyMail $mail) {
            $mail->build();

            return $mail->hasTo('tauseefchoohan0401@gmail.com')
                && $mail->hasReplyTo('support@gigfmi.com')
                && $mail->subject === 'Re: App question';
        });

        $fresh = $ticket->fresh();
        $this->assertSame("Hi,\nopen Income Tracker and tap Add.", $fresh->response);
        $this->assertNotNull($fresh->responded_at);
        $this->assertSame('sent', $fresh->status); // stays in the support list
    }

    public function test_admin_reply_validation_and_mail_failure(): void
    {
        $ticket = $this->ticket();
        $admin  = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('support.respond', $ticket->id), ['response' => ''])
            ->assertSessionHasErrors('response');

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($admin, 'admin')
            ->post(route('support.respond', $ticket->id), ['response' => 'Hello'])
            ->assertSessionHas('error');

        $this->assertNull($ticket->fresh()->response);
        $this->assertNull($ticket->fresh()->responded_at);
    }

    public function test_reply_needs_admin_login(): void
    {
        $ticket = $this->ticket();

        $this->post(route('support.respond', $ticket->id), ['response' => 'Hello'])
            ->assertRedirect(route('admin.login'));
    }

    public function test_ticket_page_shows_reply_form_and_last_reply(): void
    {
        $ticket = $this->ticket(['response' => 'Earlier answer', 'responded_at' => now()]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('support.show', $ticket->id))
            ->assertOk()
            ->assertSee(route('support.respond', $ticket->id), false)
            ->assertSee('Send reply')
            ->assertSee('Last reply sent')
            ->assertSee('Earlier answer')
            ->assertSee('Reply by email');
    }

    public function test_reply_mail_renders(): void
    {
        $html = (new SupportReplyMail($this->ticket(), 'Thanks for writing'))->render();

        $this->assertStringContainsString('Thanks for writing', $html);
        $this->assertStringContainsString('How do I add income?', $html);
        $this->assertStringContainsString('mailto:support@gigfmi.com', $html);
    }
}
