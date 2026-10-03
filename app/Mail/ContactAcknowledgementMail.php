<?php

namespace App\Mail;

use App\Models\BusinessProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactAcknowledgementMail extends Mailable
{
    use Queueable, SerializesModels;

    public BusinessProfile $business;

    public function __construct(public array $payload)
    {
        $this->business = BusinessProfile::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'We received your message - '.$this->business->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-acknowledgement');
    }
}
