<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        // Reply in the support mailbox goes straight to the customer (when they have an email)
        if (! empty($this->data['email'])) {
            $this->replyTo($this->data['email'], $this->data['name'] ?? null);
        }

        return $this->subject($this->data['subject'] ?? 'Support Request')
            ->view('mails.support') // ✅ sahi path
            ->with(['data' => $this->data]);
    }
}
