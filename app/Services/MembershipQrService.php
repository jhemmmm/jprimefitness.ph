<?php

namespace App\Services;

use App\Mail\MembershipQrCodeMail;
use App\Models\MemberSubscription;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MembershipQrService
{
    public const PAYLOAD_PREFIX = 'JPRIME:';

    /**
     * Ensure a membership subscription has a QR payload.
     *
     * @return MemberSubscription
     */
    public function ensurePayload(MemberSubscription $subscription): MemberSubscription
    {
        if (filled($subscription->qr_payload)) {
            return $subscription;
        }

        $subscription->forceFill([
            'qr_payload' => self::PAYLOAD_PREFIX.Str::random(40),
            'qr_generated_at' => now(),
        ])->save();

        return $subscription->fresh(['member', 'ratePlan']);
    }

    /**
     * Send the membership QR code to the member email address.
     *
     * @return void
     */
    public function sendEmail(MemberSubscription $subscription): void
    {
        $subscription = $this->ensurePayload($subscription)->fresh(['member', 'ratePlan']);

        if (! $subscription->member?->email) {
            return;
        }

        Mail::to($subscription->member->email)
            ->send(new MembershipQrCodeMail($subscription, $this->dataUri($subscription)));

        $subscription->forceFill([
            'qr_emailed_at' => now(),
        ])->save();
    }

    /**
     * Build the panel modal payload for a membership QR code.
     *
     * @return array<string, mixed>
     */
    public function modalPayload(MemberSubscription $subscription): array
    {
        $subscription = $this->ensurePayload($subscription)->fresh(['member', 'ratePlan']);

        return [
            'membership_id' => $subscription->id,
            'member_name' => $subscription->member?->name,
            'member_email' => $subscription->member?->email,
            'plan_name' => $subscription->ratePlan?->name,
            'status' => $subscription->status,
            'start_date' => $subscription->start_date?->toDateString(),
            'end_date' => $subscription->end_date?->toDateString(),
            'qr_payload' => $subscription->qr_payload,
            'qr_data_uri' => $this->dataUri($subscription),
            'qr_generated_at' => $subscription->qr_generated_at?->toISOString(),
            'qr_emailed_at' => $subscription->qr_emailed_at?->toISOString(),
        ];
    }

    /**
     * Render the QR code as an SVG data URI.
     *
     * @return string
     */
    public function dataUri(MemberSubscription $subscription): string
    {
        $subscription = $this->ensurePayload($subscription);

        $writer = new SvgWriter;
        $qrCode = new QrCode(
            data: (string) $subscription->qr_payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $writer->write($qrCode)->getDataUri();
    }
}
