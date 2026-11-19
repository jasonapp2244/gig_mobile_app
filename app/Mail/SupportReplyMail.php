<?php

namespace App\Mail;

use App\Models\SupportEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Admin panel reply to a support request; the customer's Reply goes to the support mailbox. */
class SupportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupportEmail $support, public string $reply)
    {
    }

    public function build()
    {
        return $this->subject('Re: ' . ($this->support->subject ?: 'Your support request'))
            ->replyTo(config('mail.support.address'), config('mail.support.name'))
            ->view('mails.support_reply')
            ->with([
                'support' => $this->support,
                'reply'   => $this->reply,
            ]);
    }
}
