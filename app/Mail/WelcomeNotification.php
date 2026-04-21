<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $otp)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Task Scheduler - Verify Your Email')
            ->line('Your OTP for email verification is: ' . $this->otp)
            ->line('This OTP will expire in 10 minutes.')
            ->line('If you did not request this, please ignore this email.');
    }
}
