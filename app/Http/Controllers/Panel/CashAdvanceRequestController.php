<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
use App\Models\User;
use App\Notifications\CashAdvanceRequestedNotification;
use App\Notifications\CashAdvanceRequestReviewedNotification;
use App\Services\NotificationRecipientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee cash advance requests. Employees request/withdraw their own;
 * managers approve (which records the real CashAdvance) or reject.
 * A request never touches the cash drawer or payroll until approved.
 */
class CashAdvanceRequestController extends Controller
{
    public function __construct(
        private NotificationRecipientResolver $notificationRecipientResolver,
    ) {
        $this->middleware('can:manage employees')->only(['approve', 'reject']);
    }

    /**
     * List an employee's requests, newest first.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(User $employee): JsonResponse
    {
        abort_unless($employee->is(auth()->user()) || auth()->user()->can('manage employees'), 403);

        $requests = CashAdvanceRequest::query()
            ->where('employee_id', $employee->id)
            ->with('reviewedBy:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CashAdvanceRequest $request) => $this->serialize($request))
            ->values();

        return response()->json($requests);
    }

    /**
     * Submit a request for the signed-in employee.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, User $employee): JsonResponse
    {
        abort_unless($employee->is(auth()->user()), 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if (CashAdvanceRequest::query()->where('employee_id', $employee->id)->pending()->exists()) {
            throw ValidationException::withMessages([
                'amount' => ['You already have a pending cash advance request.'],
            ]);
        }

        $advanceRequest = CashAdvanceRequest::create([
            'employee_id' => $employee->id,
            'amount' => $data['amount'],
            'reason' => trim($data['reason']),
            'status' => CashAdvanceRequest::STATUS_PENDING,
        ]);

        $this->notificationRecipientResolver->send(new CashAdvanceRequestedNotification($advanceRequest));

        return response()->json($this->serialize($advanceRequest), 201);
    }

    /**
     * Withdraw the signed-in employee's own pending request.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdraw(User $employee, CashAdvanceRequest $cashAdvanceRequest): JsonResponse
    {
        abort_unless($employee->is(auth()->user()), 403);
        $this->assertBelongsTo($employee, $cashAdvanceRequest);
        abort_unless($cashAdvanceRequest->isPending(), 409, 'Only pending requests can be withdrawn.');

        $cashAdvanceRequest->update(['status' => CashAdvanceRequest::STATUS_WITHDRAWN]);

        return response()->json($this->serialize($cashAdvanceRequest));
    }

    /**
     * Approve a request: records the cash advance (same rules as a manual
     * one, so the ledger/payroll side is untouched) and notifies the employee.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(Request $request, User $employee, CashAdvanceRequest $cashAdvanceRequest): JsonResponse
    {
        $this->assertReviewable($employee, $cashAdvanceRequest);

        $data = $request->validate(CashAdvance::releaseRules());

        $reviewed = DB::transaction(function () use ($cashAdvanceRequest, $employee, $data) {
            $locked = $this->lockPending($cashAdvanceRequest);
            $advance = CashAdvance::release($employee, $locked->amount, [
                ...$data,
                'notes' => $data['notes'] ?? $locked->reason,
            ]);

            $locked->update([
                'status' => CashAdvanceRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $data['notes'] ?? null,
                'cash_advance_id' => $advance->id,
            ]);

            return $locked;
        });

        $employee->notify(new CashAdvanceRequestReviewedNotification($reviewed));

        return response()->json($this->serialize($reviewed));
    }

    /**
     * Reject a request with a reason and notify the employee.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, User $employee, CashAdvanceRequest $cashAdvanceRequest): JsonResponse
    {
        $this->assertReviewable($employee, $cashAdvanceRequest);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $reviewed = DB::transaction(function () use ($cashAdvanceRequest, $data) {
            return tap($this->lockPending($cashAdvanceRequest))->update([
                'status' => CashAdvanceRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => trim($data['reason']),
            ]);
        });

        $employee->notify(new CashAdvanceRequestReviewedNotification($reviewed));

        return response()->json($this->serialize($reviewed));
    }

    private function assertBelongsTo(User $employee, CashAdvanceRequest $cashAdvanceRequest): void
    {
        abort_if($cashAdvanceRequest->employee_id !== $employee->id, 404);
    }

    private function assertReviewable(User $employee, CashAdvanceRequest $cashAdvanceRequest): void
    {
        $this->assertBelongsTo($employee, $cashAdvanceRequest);
        abort_if($employee->is(auth()->user()), 403, 'You cannot review your own cash advance request.');
        abort_unless($cashAdvanceRequest->isPending(), 409, 'This request has already been reviewed.');
    }

    /**
     * Re-read under a row lock so two reviewers can't both approve/reject the same request.
     * Call inside a transaction.
     */
    private function lockPending(CashAdvanceRequest $cashAdvanceRequest): CashAdvanceRequest
    {
        $locked = CashAdvanceRequest::whereKey($cashAdvanceRequest->getKey())->lockForUpdate()->firstOrFail();
        abort_unless($locked->isPending(), 409, 'This request has already been reviewed.');

        return $locked;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CashAdvanceRequest $request): array
    {
        $request->loadMissing('reviewedBy:id,name');

        return [
            'id' => $request->id,
            'amount' => (float) $request->amount,
            'reason' => $request->reason,
            'status' => $request->status,
            'requested_at' => $request->created_at?->toISOString(),
            'reviewed_by_name' => $request->reviewedBy?->name,
            'reviewed_at' => $request->reviewed_at?->toISOString(),
            'review_note' => $request->review_note,
            'cash_advance_id' => $request->cash_advance_id,
        ];
    }
}
