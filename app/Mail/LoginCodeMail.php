<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Second sign-in step for partner staff: a 6-digit code by email. */
class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Southview Park partner sign-in code: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.login-code');
    }
}
