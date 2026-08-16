<?php

namespace App\Services;

use App\Models\MemberPtPackage;
use App\Models\Payroll;
use App\Models\SystemActivity;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class MemberPtPackageService
{
    public function __construct(
        private PayrollService $payrollService,
        private SystemActivityService $systemActivityService,
    ) {}

    /**
     * Cancel an unused PT package while preserving its audit history.
     *
     * @param  array<string, mixed>|null  $causedBy
     * @return \App\Models\MemberPtPackage
     */
    public function cancelUnused(
        MemberPtPackage $memberPtPackage,
        User $cancelledBy,
        string $reason,
        ?array $causedBy = null,
        DateTimeInterface|string|null $cancelledAt = null,
    ): MemberPtPackage {
        return DB::transaction(function () use ($memberPtPackage, $cancelledBy, $reason, $causedBy, $cancelledAt): MemberPtPackage {
            $package = MemberPtPackage::query()
                ->with(['saleTransaction:id,sold_at', 'member:id,name', 'ptProduct:id,name'])
                ->withCount('usages')
                ->lockForUpdate()
                ->findOrFail($memberPtPackage->id);

            if ($package->status === MemberPtPackage::STATUS_CANCELLED) {
                abort(409, 'This PT package has already been cancelled.');
            }

            if ($package->usages_count > 0) {
                abort(409, 'This PT package cannot be cancelled because sessions have already been used.');
            }

            $blockingPayroll = $this->payrollService->commissionPayrollBlockingCancellation($package);

            if ($blockingPayroll) {
                $message = $blockingPayroll->status === Payroll::STATUS_DRAFT
                    ? "This PT package cannot be cancelled because its commission is included in draft payroll #{$blockingPayroll->id}. Cancel that payroll first."
                    : "This PT package cannot be cancelled because its commission is included in finalized payroll #{$blockingPayroll->id}. A payroll adjustment is required before this sale can be voided.";

                abort(409, $message);
            }

            $cancelledAt ??= now();

            $package->forceFill([
                'status' => MemberPtPackage::STATUS_CANCELLED,
                'remaining_sessions' => $package->total_sessions,
                'cancellation_reason' => $reason,
                'cancelled_by' => $cancelledBy->id,
                'cancelled_at' => $cancelledAt,
            ])->save();

            $package->load(['ptProduct', 'coach', 'member', 'cancelledBy', 'saleTransaction']);

            $this->systemActivityService->recordSubjectEvent(
                SystemActivity::SUBJECT_MEMBER_PT_PACKAGE,
                $package->id,
                'cancelled',
                $this->systemActivitySnapshot($package),
                array_filter([
                    'reason' => $reason,
                    'caused_by' => $causedBy,
                ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                $cancelledBy->id,
                $cancelledBy->name,
                $cancelledAt,
            );

            return $package;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function systemActivitySnapshot(MemberPtPackage $package): array
    {
        return [
            'id' => $package->id,
            'member_id' => $package->user_id,
            'member_name' => $package->member?->name ?? 'Unknown Member',
            'pt_product_id' => $package->pt_product_id,
            'product_name' => $package->ptProduct?->name,
            'coach_id' => $package->coach_id,
            'coach_name' => $package->coach?->name,
            'total_sessions' => $package->total_sessions,
            'remaining_sessions' => $package->remaining_sessions,
            'assigned_at' => $package->assigned_at?->toDateString(),
            'status' => $package->status,
            'cancellation_reason' => $package->cancellation_reason,
            'cancelled_by' => $package->cancelledBy?->name,
            'cancelled_at' => $package->cancelled_at?->toDateTimeString(),
        ];
    }
}
