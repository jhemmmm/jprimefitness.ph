<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\KioskPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KioskAttendanceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $type = $request->input('type');

        if ($type === 'walk_in') {
            return $this->storeWalkIn($request);
        }

        if ($type === 'member') {
            return $this->storeMember($request);
        }

        return response()->json([
            'ok' => false,
            'message' => 'Invalid kiosk attendance type.',
        ], 422);
    }

    private function storeWalkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['walk_in'])],
            'status' => ['required', Rule::in(['success', 'failed'])],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:32'],
            'payment_method' => ['required', Rule::in(['counter', 'online'])],
            'payment_status' => ['required', Rule::in(['pending', 'paid', 'timeout', 'cancelled'])],
            'payment_reference' => ['nullable', 'string', 'max:64'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $occurredAt = isset($data['occurred_at'])
            ? Carbon::parse($data['occurred_at'])
            : Carbon::now();

        // Failed walk-ins are not recorded as attendance — they're audited via logs
        // and (for online) the kiosk_payments row itself. We acknowledge the call.
        if ($data['status'] !== 'success') {
            return response()->json([
                'ok' => false,
                'message' => $this->walkInFailureMessage($data),
            ]);
        }

        $kioskPayment = $this->markSuccessfulOnlinePayment($data, $occurredAt);

        $attendance = Attendance::create([
            'attendee_type' => Attendance::TYPE_WALK_IN,
            'user_id' => null,
            'name' => $data['name'],
            'checked_in_at' => $occurredAt,
            'checked_out_at' => null,
            'notes' => $this->encodeWalkInNotes($data, $kioskPayment),
            'recorded_by' => null,
            'source' => Attendance::SOURCE_KIOSK,
            'source_device_serial' => $request->header('X-Kiosk-Device'),
        ]);

        return response()->json([
            'ok' => true,
            'attendance_id' => $attendance->id,
            'member_name' => null,
            'message' => $this->walkInSuccessMessage($data),
        ], 201);
    }

    private function storeMember(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['member'])],
            'status' => ['required', Rule::in(['success', 'failed'])],
            'action' => ['required', Rule::in(['time_in', 'time_out'])],
            'qr_payload' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', Rule::in(['unknown_qr', 'expired'])],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $occurredAt = isset($data['occurred_at'])
            ? Carbon::parse($data['occurred_at'])
            : Carbon::now();

        // Resolve the member from the QR payload regardless of the kiosk's
        // claimed status — the server is the source of truth.
        $user = $this->resolveMemberFromQr($data['qr_payload']);

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'QR code not recognized.',
            ]);
        }

        if ($data['action'] === 'time_in') {
            $attendance = Attendance::create([
                'attendee_type' => Attendance::TYPE_MEMBER,
                'user_id' => $user->id,
                'name' => $user->name,
                'checked_in_at' => $occurredAt,
                'checked_out_at' => null,
                'notes' => null,
                'recorded_by' => null,
                'source' => Attendance::SOURCE_KIOSK,
                'source_device_serial' => $request->header('X-Kiosk-Device'),
            ]);

            return response()->json([
                'ok' => true,
                'attendance_id' => $attendance->id,
                'member_name' => $user->name,
                'message' => 'Welcome back, '.$user->name,
            ], 201);
        }

        // time_out: close the latest open attendance for this member
        $open = Attendance::where('user_id', $user->id)
            ->where('attendee_type', Attendance::TYPE_MEMBER)
            ->whereNull('checked_out_at')
            ->orderByDesc('checked_in_at')
            ->first();

        if (! $open) {
            return response()->json([
                'ok' => false,
                'member_name' => $user->name,
                'message' => 'No open session was found for this member.',
            ]);
        }

        $open->update(['checked_out_at' => $occurredAt]);

        return response()->json([
            'ok' => true,
            'attendance_id' => $open->id,
            'member_name' => $user->name,
            'message' => 'Goodbye, '.$user->name,
        ]);
    }

    private function resolveMemberFromQr(string $payload): ?User
    {
        if (! preg_match('/^JPRIME:MEMBER:(\d+)$/', $payload, $m)) {
            return null;
        }

        $user = User::query()->find((int) $m[1]);

        if (! $user || ! $user->hasRole('member')) {
            return null;
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function markSuccessfulOnlinePayment(array $data, Carbon $paidAt): ?KioskPayment
    {
        if (($data['payment_method'] ?? null) !== 'online' || empty($data['payment_reference'])) {
            return null;
        }

        $payment = KioskPayment::where('reference', $data['payment_reference'])->first();

        if (! $payment) {
            return null;
        }

        if ($payment->status !== KioskPayment::STATUS_PAID || $payment->paid_at === null) {
            $payment->update([
                'status' => KioskPayment::STATUS_PAID,
                'paid_at' => $paidAt,
            ]);
        }

        return $payment->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encodeWalkInNotes(array $data, ?KioskPayment $kioskPayment = null): string
    {
        $payload = [
            'phone' => $data['phone'],
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
        ];

        if (! empty($data['payment_reference'])) {
            $payload['payment_reference'] = $data['payment_reference'];
            if ($kioskPayment) {
                $payload['amount'] = $kioskPayment->getAmountPesos();
            }
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function walkInSuccessMessage(array $data): string
    {
        return $data['payment_method'] === 'counter'
            ? 'Walk-in recorded — please proceed to the counter.'
            : 'Walk-in recorded — payment received.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function walkInFailureMessage(array $data): string
    {
        return $data['payment_method'] === 'online'
            ? 'Online payment was not completed.'
            : 'Walk-in could not be recorded.';
    }
}
