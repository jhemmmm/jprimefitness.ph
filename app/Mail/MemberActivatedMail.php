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

class MemberActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public BusinessProfile $business;

    public function __construct(
        public User $member,
        public MemberSubscription $subscription,
    ) {
        $this->business = BusinessProfile::current();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your JPrime Fitness membership is active',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.member-activated');
    }
}
