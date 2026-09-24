<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $messageBody,
        public array $context = []
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NTSA Traffic Demerit Update: ' . $this->title
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.system-event'
        );
    }
}
