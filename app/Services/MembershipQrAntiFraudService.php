<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\MemberSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MembershipQrAntiFraudService
{
    /**
     * Validate an encrypted membership QR payload for a kiosk action.
     *
     * @return array{
     *     allowed: bool,
     *     reason: ?string,
     *     message: string,
     *     membership: ?\App\Models\MemberSubscription,
     *     member: ?\App\Models\User,
     *     open_attendance: ?\App\Models\Attendance
     * }
     */
    public function validate(string $payload, string $action, Carbon $occurredAt, ?string $deviceSerial = null): array
    {
        $membershipId = $this->decryptMembershipId($payload);

        if (! $membershipId) {
            return $this->reject('invalid_qr', 'QR code not recognized.', $action, $deviceSerial);
        }

        $membership = MemberSubscription::query()
            ->with(['member', 'ratePlan'])
            ->find($membershipId);

        if (! $membership) {
            return $this->reject('membership_missing', 'Membership was not found.', $action, $deviceSerial, $membershipId);
        }

        $member = $membership->member;

        if (! $member || ! $member->hasRole('member')) {
            return $this->reject('member_missing', 'Membership owner was not found.', $action, $deviceSerial, $membershipId, $membership, $member);
        }

        if ($member->status !== User::STATUS_ACTIVE) {
            return $this->reject('member_inactive', 'Member account is not active.', $action, $deviceSerial, $membershipId, $membership, $member);
        }

        if ($membership->status !== MemberSubscription::STATUS_ACTIVE) {
            return $this->reject('membership_inactive', 'Membership is not active.', $action, $deviceSerial, $membershipId, $membership, $member);
        }

        if ($membership->start_date && $membership->start_date->copy()->startOfDay()->greaterThan($occurredAt->copy()->startOfDay())) {
            return $this->reject('membership_not_started', 'Membership has not started yet.', $action, $deviceSerial, $membershipId, $membership, $member);
        }

        if ($membership->end_date && $membership->end_date->copy()->endOfDay()->lessThan($occurredAt)) {
            return $this->reject('membership_expired', 'Membership has expired.', $action, $deviceSerial, $membershipId, $membership, $member);
        }

        $openAttendance = Attendance::query()
            ->where('user_id', $member->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER)
            ->whereNull('checked_out_at')
            ->orderByDesc('checked_in_at')
            ->first();

        if ($action === 'time_in' && $openAttendance) {
            return $this->reject('duplicate_time_in', 'This member is already checked in.', $action, $deviceSerial, $membershipId, $membership, $member, $openAttendance);
        }

        if ($action === 'time_out' && ! $openAttendance) {
            return $this->reject('missing_open_attendance', 'No open session was found for this member.', $action, $deviceSerial, $membershipId, $membership, $member);
        }

        return [
            'allowed' => true,
            'reason' => null,
            'message' => 'Membership QR accepted.',
            'membership' => $membership,
            'member' => $member,
            'open_attendance' => $openAttendance,
        ];
    }

    /**
     * Decrypt the membership subscription ID from a QR payload.
     *
     * @return int|null
     */
    private function decryptMembershipId(string $payload): ?int
    {
        if (! Str::startsWith($payload, MembershipQrService::PAYLOAD_PREFIX)) {
            return null;
        }

        $ciphertext = Str::after($payload, MembershipQrService::PAYLOAD_PREFIX);

        try {
            $decrypted = Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            return null;
        }

        if (! ctype_digit($decrypted)) {
            return null;
        }

        return (int) $decrypted;
    }

    /**
     * Build and log a rejected anti-fraud decision.
     *
     * @return array{
     *     allowed: false,
     *     reason: string,
     *     message: string,
     *     membership: ?\App\Models\MemberSubscription,
     *     member: ?\App\Models\User,
     *     open_attendance: ?\App\Models\Attendance
     * }
     */
    private function reject(
        string $reason,
        string $message,
        string $action,
        ?string $deviceSerial = null,
        ?int $membershipId = null,
        ?MemberSubscription $membership = null,
        ?User $member = null,
        ?Attendance $openAttendance = null,
    ): array {
        Log::warning('Kiosk membership QR rejected', [
            'reason' => $reason,
            'action' => $action,
            'device_serial' => $deviceSerial,
            'membership_id' => $membershipId,
            'member_id' => $member?->id,
            'open_attendance_id' => $openAttendance?->id,
        ]);

        return [
            'allowed' => false,
            'reason' => $reason,
            'message' => $message,
            'membership' => $membership,
            'member' => $member,
            'open_attendance' => $openAttendance,
        ];
    }
}
