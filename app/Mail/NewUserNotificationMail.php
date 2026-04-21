<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewUserNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $userEmail;
    public $userPhone;
    public $registrationDate;

    /**
     * Create a new message instance.
     * Pass user data instead of the User model to avoid serialization issues
     */
    public function __construct($userName, $userEmail, $userPhone = null, $registrationDate = null)
    {
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->userPhone = $userPhone;
        $this->registrationDate = $registrationDate ?? now()->format('Y-m-d H:i:s');
    }

    public function build()
    {
        return $this->subject('New User Registration: ' . $this->userName)
                    ->view('mails.new_user_notification');
    }
}
