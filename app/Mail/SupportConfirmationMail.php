<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        // The customer's Reply goes to the support mailbox
        return $this->subject('Your Support Request has been Received')
            ->replyTo(config('mail.support.address'), config('mail.support.name'))
            ->view('mails.support_confirmation') 
            ->with(['data' => $this->data]);
    }
}
