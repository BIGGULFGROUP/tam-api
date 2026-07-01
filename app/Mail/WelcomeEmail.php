<?php

namespace App\Mail;

use App\Models\SiteSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $displayName,
        public readonly SiteSetting $settings,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->settings->welcome_email_subject
            ?: 'Welcome to The African Mail';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(html: 'emails.welcome');
    }
}
