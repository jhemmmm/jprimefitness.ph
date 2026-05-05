<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\KioskPayment;
use App\Models\User;
use App\Services\MembershipQrAntiFraudService;
use App\Services\PosSaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KioskAttendanceController extends Controller
{
    public function __construct(
        private MembershipQrAntiFraudService $membershipQrAntiFraudService,
        private PosSaleService $posSaleService,
    ) {}

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
            'discount_type' => ['nullable', Rule::in([KioskPayment::DISCOUNT_STUDENT, KioskPayment::DISCOUNT_SENIOR])],
        ]);

        $occurredAt = Carbon::now();

        // Failed walk-ins are not recorded as attendance - they're audited via logs
        // and (for online) the kiosk_payments row itself. We acknowledge the call.
        if ($data['status'] !== 'success') {
            return response()->json([
                'ok' => false,
                'message' => $this->walkInFailureMessage($data),
            ]);
        }

        $attendance = DB::transaction(function () use ($data, $occurredAt, $request) {
            $payment = $this->consumeOnlinePayment($data);

            $attendance = Attendance::create([
                'attendee_type' => Attendance::TYPE_WALK_IN,
                'user_id' => null,
                'name' => $data['name'],
                'checked_in_at' => $occurredAt,
                'checked_out_at' => null,
                'notes' => $this->encodeWalkInNotes($data, $payment),
                'recorded_by' => null,
                'source' => Attendance::SOURCE_KIOSK,
                'source_device_serial' => $request->header('X-Kiosk-Device'),
            ]);

            if ($payment) {
                $this->posSaleService->recordKioskWalkInSale($payment);
            }

            return $attendance;
        });

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
            'action' => ['required', Rule::in(['time_in', 'time_out'])],
            'qr_payload' => ['required', 'string', 'max:2000'],
        ]);

        $occurredAt = Carbon::now();

        $decision = $this->membershipQrAntiFraudService->validate(
            $data['qr_payload'],
            $data['action'],
            $occurredAt,
            $request->header('X-Kiosk-Device'),
        );

        if (! $decision['allowed']) {
            return response()->json([
                'ok' => false,
                'member_name' => $decision['member']?->name,
                'reason' => $decision['reason'],
                'message' => $decision['message'],
            ]);
        }

        $user = $decision['member'];

        if ($data['action'] === 'time_in') {
            $attendance = $this->createMemberTimeIn($user, $occurredAt, $request);

            if ($attendance === null) {
                return response()->json([
                    'ok' => false,
                    'member_name' => $user->name,
                    'reason' => 'duplicate_time_in',
                    'message' => 'This member is already checked in.',
                ]);
            }

            return response()->json([
                'ok' => true,
                'attendance_id' => $attendance->id,
                'member_name' => $user->name,
                'message' => 'Welcome back, '.$user->name,
            ], 201);
        }

        $open = $decision['open_attendance'];
        $open->update(['checked_out_at' => $occurredAt]);

        return response()->json([
            'ok' => true,
            'attendance_id' => $open->id,
            'member_name' => $user->name,
            'message' => 'Goodbye, '.$user->name,
        ]);
    }

    /**
     * Atomically create a member time-in row, guarding against two concurrent
     * scans both passing the anti-fraud "no open attendance" check and inserting.
     * Returns null when an open attendance already exists (lost the race).
     */
    private function createMemberTimeIn(User $user, Carbon $occurredAt, Request $request): ?Attendance
    {
        return DB::transaction(function () use ($user, $occurredAt, $request) {
            // Lock the user row for the duration of the transaction so concurrent
            // time_in calls for the same member serialize.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $alreadyOpen = Attendance::query()
                ->where('user_id', $user->id)
                ->where('attendee_type', Attendance::TYPE_MEMBER)
                ->whereNull('checked_out_at')
                ->exists();

            if ($alreadyOpen) {
                return null;
            }

            return Attendance::create([
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
        });
    }

    /**
     * Resolve and consume the referenced online kiosk payment (if any).
     *
     * Will only consume payments that have already been verified as PAID via
     * KioskPaymentController::confirm (placeholder for a real GCash/PayMongo
     * webhook). Returns null when the row isn't on this node - by design the
     * online-payment row lives on the production backend (the only host
     * PayMongo's webhook can reach), while attendance is always recorded
     * locally. Throws ValidationException only when the row IS local but is
     * unpaid or already consumed.
     *
     * @param  array<string, mixed>  $data
     */
    private function consumeOnlinePayment(array $data): ?KioskPayment
    {
        if (($data['payment_method'] ?? null) !== 'online' || empty($data['payment_reference'])) {
            return null;
        }

        $payment = KioskPayment::query()
            ->where('reference', $data['payment_reference'])
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            Log::info('Online walk-in payment row not on local node; recording attendance only', [
                'payment_reference' => $data['payment_reference'],
            ]);

            return null;
        }

        if ($payment->consumed_at !== null) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Payment reference has already been used.'],
            ]);
        }

        if ($payment->status !== KioskPayment::STATUS_PAID || $payment->paid_at === null) {
            throw ValidationException::withMessages([
                'payment_reference' => ['Payment has not been verified as paid.'],
            ]);
        }

        $payment->update(['consumed_at' => Carbon::now()]);

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

        if (! empty($data['discount_type'])) {
            $payload['discount_type'] = $data['discount_type'];
        }

        if (! empty($data['payment_reference'])) {
            $payload['payment_reference'] = $data['payment_reference'];
            if ($kioskPayment) {
                $payload['amount'] = (float) $kioskPayment->amount;
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
            ? 'Walk-in recorded - please proceed to the counter.'
            : 'Walk-in recorded - payment received.';
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
