<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
use App\Models\MemberPtPackage;
use App\Models\MemberPtSessionUsage;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Self-scoped dashboard for staff and coaches. Every query is keyed on the
 * authenticated user, so the route only needs `access panel`.
 */
class MyDashboardController extends Controller
{
    /**
     * Return the current user's dashboard data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function data(): JsonResponse
    {
        return response()->json($this->payload(auth()->user()));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $me): array
    {
        $payrolls = $me->payrolls()
            ->where('status', '!=', Payroll::STATUS_CANCELED)
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Payroll $payroll) => [
                'id' => $payroll->id,
                'period_start' => $payroll->period_start?->toDateString(),
                'period_end' => $payroll->period_end?->toDateString(),
                'gross_amount' => (float) $payroll->gross_amount,
                'commission_amount' => (float) ($payroll->commission_amount ?? 0),
                'employee_contributions_total' => $payroll->employeeContributionsTotal(),
                'net_amount' => (float) $payroll->net_amount,
                'status' => $payroll->status,
                'payslip_url' => route('panel.employees.payrolls.payslip', [$me, $payroll]),
            ])
            ->values();

        $unpaidPayrolls = $me->payrolls()->whereIn('status', [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID]);
        $outstandingBalance = (float) (clone $unpaidPayrolls)->sum('net_amount')
            - (float) Payout::query()->whereIn('payroll_id', (clone $unpaidPayrolls)->select('id'))->sum('amount');

        $attendanceQuery = Attendance::query()->where('user_id', $me->id);
        $monthAttendance = (clone $attendanceQuery)
            ->whereBetween('checked_in_at', [now()->startOfMonth(), now()->endOfDay()])
            ->get(['checked_in_at', 'checked_out_at']);
        $monthMinutes = $monthAttendance->sum(fn (Attendance $a) => $a->workedMinutes() ?? 0);

        $payload = [
            'is_coach' => $me->can('log pt sessions'),
            'stats' => [
                'latest_payroll' => $payrolls->first(),
                'outstanding_payroll_balance' => round(max(0, $outstandingBalance), 2),
                'attendance_this_month' => [
                    'days' => $monthAttendance->count(),
                    'hours' => round($monthMinutes / 60, 1),
                ],
                'currently_in' => (clone $attendanceQuery)->whereNull('checked_out_at')->exists(),
                'cash_advance_balance' => CashAdvance::outstandingFor($me),
                'pending_cash_advance_requests' => CashAdvanceRequest::query()->where('employee_id', $me->id)->pending()->count(),
            ],
            'payrolls' => $payrolls,
            'attendance' => (clone $attendanceQuery)
                ->orderByDesc('checked_in_at')
                ->limit(5)
                ->get()
                ->map(fn (Attendance $a) => [
                    'id' => $a->id,
                    'checked_in_at' => $a->checked_in_at?->toISOString(),
                    'checked_out_at' => $a->checked_out_at?->toISOString(),
                    'source' => $a->source,
                ])
                ->values(),
        ];

        if (! $payload['is_coach']) {
            return $payload;
        }

        // Coach block: only the member's name crosses the wire — no contact details.
        $trainees = MemberPtPackage::query()
            ->where('coach_id', $me->id)
            ->where('status', MemberPtPackage::STATUS_ACTIVE)
            ->with(['member:id,name', 'ptProduct:id,name'])
            ->withMax('usages as last_session_at', 'used_at')
            ->orderBy('expires_at')
            ->get()
            ->map(fn (MemberPtPackage $package) => [
                'id' => $package->id,
                'member_name' => $package->member?->name,
                'plan_name' => $package->ptProduct?->name,
                'remaining_sessions' => (int) $package->remaining_sessions,
                'total_sessions' => (int) $package->total_sessions,
                'expires_at' => $package->expires_at?->toDateString(),
                'last_session_at' => $package->last_session_at,
            ])
            ->values();

        $payload['stats']['active_trainees'] = $trainees->count();
        $payload['stats']['sessions_this_month'] = (int) MemberPtSessionUsage::query()
            ->where('coach_id', $me->id)
            ->whereBetween('used_at', [now()->startOfMonth(), now()->endOfDay()])
            ->sum('sessions_used');
        $payload['trainees'] = $trainees;
        $payload['recent_sessions'] = MemberPtSessionUsage::query()
            ->where('coach_id', $me->id)
            ->with('memberPtPackage.member:id,name')
            ->orderByDesc('used_at')
            ->limit(10)
            ->get()
            ->map(fn (MemberPtSessionUsage $usage) => [
                'id' => $usage->id,
                'member_name' => $usage->memberPtPackage?->member?->name,
                'sessions_used' => (int) $usage->sessions_used,
                'used_at' => $usage->used_at?->toISOString(),
                'notes' => $usage->notes,
            ])
            ->values();

        return $payload;
    }
}
