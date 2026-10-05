<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResidentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $title, public ?string $body, public ?string $url, public ?string $cta = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title.' | Southview Park');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification');
    }
}
