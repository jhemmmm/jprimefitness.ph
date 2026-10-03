<?php

namespace App\Mail;

use App\Models\BusinessProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
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
            subject: 'New contact form message - '.$this->payload['topic'],
            replyTo: [new Address($this->payload['email'], $this->payload['name'])],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
