<?php

namespace App\Mail;

use App\Models\BusinessProfile;
use App\Models\MemberSubscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberRegistrationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public BusinessProfile $business;

    public function __construct(
        public User $member,
        public MemberSubscription $subscription,
        public string $paymentMethod,
        public bool $renewal = false,
    ) {
        $this->business = BusinessProfile::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->renewal ? 'We received your JPrime Fitness renewal' : 'We received your JPrime Fitness registration',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-received');
    }
}
