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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * "Expires in N days" reminder, or the "has expired" notice when $daysRemaining is null.
 */
class MembershipExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $member,
        public MemberSubscription $subscription,
        public ?int $daysRemaining = null,
    ) {}

    /** "today" / "in 1 day" / "in N days"; null once expired. */
    public function dueIn(): ?string
    {
        return match (true) {
            $this->daysRemaining === null => null,
            $this->daysRemaining === 0 => 'today',
            default => 'in '.$this->daysRemaining.' '.Str::plural('day', $this->daysRemaining),
        };
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->dueIn() === null
                ? 'Your JPrime Fitness membership has expired'
                : "Your JPrime Fitness membership expires {$this->dueIn()}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.membership-expiry', with: [
            'business' => BusinessProfile::current(),
            'when' => $this->dueIn(),
            // signed link to the unlisted renew page, pre-filled for this member
            'renewUrl' => URL::signedRoute('renew', ['email' => $this->member->email]),
        ]);
    }
}
