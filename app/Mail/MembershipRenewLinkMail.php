<?php

namespace App\Mail;

use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Sent when someone submits the public sign-up form with an email that already
 * belongs to a member: the inbox owner gets the signed link to renew instead.
 */
class MembershipRenewLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $member) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your JPrime Fitness renewal link',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.membership-renew-link', with: [
            'business' => BusinessProfile::current(),
            'renewUrl' => URL::signedRoute('renew', ['email' => $this->member->email]),
        ]);
    }
}
