<?php

namespace App\Mail;

use App\Models\MemberSubscription;
use App\Services\MembershipQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipQrCodeMail extends Mailable implements ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(
        public MemberSubscription $subscription,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your JPrime Fitness Membership QR Code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-qr-code',
            with: [
                'qrPng' => app(MembershipQrService::class)->pngBytes($this->subscription),
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
