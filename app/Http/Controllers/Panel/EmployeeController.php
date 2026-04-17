<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\CashAdvance;
use App\Models\EmployeeProfile;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use App\Notifications\CashAdvanceStatusChangedNotification;
use App\Notifications\PayrollApprovedNotification;
use App\Services\AuditHistoryService;
use App\Services\CashLedgerService;
use App\Services\NotificationRecipientResolver;
use App\Services\PayrollService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Facades\Pdf;

class EmployeeController extends Controller
{
    public function __construct(
        private PayrollService $payrollService,
        private CashLedgerService $cashLedgerService,
        private NotificationRecipientResolver $notificationRecipientResolver,
        private AuditHistoryService $auditHistoryService,
    ) {
        $this->middleware('can:manage employees');
    }

    public function index(): View
    {
        return view('panel.employees.index');
    }

    public function show(User $employee): View
    {
        return view('panel.employees.show', [
            'employee' => $this->serializeEmployee($employee->load('roles')),
            'employeeName' => $employee->name,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $employees = User::role(['employee', 'coach', 'manager', 'admin', 'staff'])
            ->with(['roles', 'employeeProfile'])
            ->when(! empty($request->search), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(! empty($request->role), fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('id', $request->role)))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->orderBy('name')
            ->get()
            ->map(fn (User $employee) => $this->serializeEmployee($employee))
            ->values()
            ->all();

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::in(auth()->user()->allowedEmployeesRoles())],
            'employee_profile' => ['required', 'array'],
            'employee_profile.daily_rate' => ['required', 'numeric', 'min:0'],
            'employee_profile.pay_frequency' => ['required', Rule::in(['monthly', 'semi_monthly'])],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'password' => Hash::make($data['password']),
        ]);

        $employee->roles()->attach($data['role_ids']);
        $employee = $employee->fresh()->load('roles');
        $this->ensureEmployeeProfile($employee, $data['employee_profile']);
        $employee = $employee->fresh()->load(['roles', 'employeeProfile']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'created',
            $this->employeeAuditSnapshot($employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializeEmployee($employee), 201);
    }

    public function update(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($employee->id)->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::in(auth()->user()->allowedEmployeesRoles())],
            'employee_profile' => ['required', 'array'],
            'employee_profile.daily_rate' => ['required', 'numeric', 'min:0'],
            'employee_profile.pay_frequency' => ['required', Rule::in(['monthly', 'semi_monthly'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $employee->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'password' => isset($data['password']) && $data['password'] !== ''
                ? Hash::make($data['password'])
                : $employee->password,
        ]);

        $employee->roles()->sync($data['role_ids']);
        $this->ensureEmployeeProfile($employee, $data['employee_profile']);
        $employee = $employee->fresh()->load(['roles', 'employeeProfile']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'updated',
            $this->employeeAuditSnapshot($employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializeEmployee($employee));
    }

    public function destroy(User $employee): JsonResponse
    {
        abort_if($employee->id === auth()->id(), 403);
        $snapshot = $this->employeeAuditSnapshot($employee->loadMissing('roles'));

        $employee->delete();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_EMPLOYEE,
            $employee->id,
            'deleted',
            $snapshot,
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['message' => 'Employee deleted.']);
    }

    public function attendance(Request $request, User $employee): JsonResponse
    {
        $records = Attendance::query()
            ->where('user_id', $employee->id)
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->paginate(15)
            ->through(fn (Attendance $attendance) => [
                'id' => $attendance->id,
                'attendee_type' => $attendance->attendee_type,
                'name' => $attendance->name,
                'checked_in_at' => $attendance->checked_in_at?->toISOString(),
                'checked_out_at' => $attendance->checked_out_at?->toISOString(),
                'notes' => $attendance->notes,
                'source' => $attendance->source,
                'source_device_serial' => $attendance->source_device_serial,
            ]);

        $statsQuery = Attendance::query()->where('user_id', $employee->id);

        return response()->json([
            'records' => $records,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'this_month' => (clone $statsQuery)->whereMonth('checked_in_at', now()->month)->whereYear('checked_in_at', now()->year)->count(),
                'currently_in' => (clone $statsQuery)->whereNull('checked_out_at')->count(),
            ],
        ]);
    }

    public function payrolls(User $employee): JsonResponse
    {
        $payrolls = Payroll::query()
            ->where('employee_id', $employee->id)
            ->with(['payouts', 'approvedBy:id,name'])
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (Payroll $payroll) => $this->serializePayroll($payroll))
            ->values()
            ->all();

        return response()->json($payrolls);
    }

    public function payslip(User $employee, Payroll $payroll): Responsable
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        $payroll->load([
            'employee.roles',
            'generatedBy:id,name',
            'approvedBy:id,name',
            'payouts' => fn ($query) => $query
                ->with('releasedBy:id,name')
                ->orderBy('paid_at'),
        ]);

        $businessProfile = BusinessProfile::current();
        $employee = $employee->fresh()->load('roles');
        $fileName = 'payslip-employee-'.$employee->id.'-payroll-'.$payroll->id.'.pdf';

        return Pdf::view('panel.employees.payslip', [
            'employee' => $employee,
            'payroll' => $payroll,
            'businessProfile' => $businessProfile,
        ])->driver('dompdf')->format('a4')->margins(8, 8, 8, 8)->download($fileName);
    }

    public function storePayroll(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
            'cash_advance_deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $businessProfile = BusinessProfile::current();
        $countryCode = $businessProfile->country_code;
        $attendanceSuggestion = $this->payrollService->suggestFromAttendance(
            $employee,
            $data['period_start'],
            $data['period_end'],
            (bool) $businessProfile->pay_overwork_hours,
        );
        $gross = (float) $data['gross_amount'];
        $bonus = (float) ($data['bonus'] ?? 0);
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceDeduction = (float) ($data['cash_advance_deduction'] ?? 0);
        $payFrequency = $this->employeePayFrequency($employee);
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'period_end' => $data['period_end'],
        ];

        $ptCommissionSummary = $this->payrollService->previewPtCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
        );
        $membershipCommissionSummary = $this->payrollService->previewMembershipCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
        );
        $maxCashAdvanceDeduction = $this->payrollService->maxCashAdvanceDeduction(
            $employee->id,
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );
        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $cashAdvanceDeduction,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );

        if ($cashAdvanceDeduction > $maxCashAdvanceDeduction) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'cash_advance_deduction' => ["Cash advance deduction may not exceed {$maxCashAdvanceDeduction}."],
                ],
            ], 422);
        }

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'pay_frequency' => $payFrequency,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'regular_hours' => $attendanceSuggestion['regular_hours'],
            'regular_pay_amount' => $attendanceSuggestion['regular_pay_amount'],
            'overwork_hours' => $attendanceSuggestion['overwork_hours'],
            'overwork_pay_amount' => $attendanceSuggestion['overwork_pay_amount'],
            'gross_amount' => $gross,
            'bonus' => $bonus,
            'income_tax' => $payrollTotals['income_tax'],
            'pt_commission_amount' => $ptCommissionSummary['amount'],
            'pt_commission_items' => $ptCommissionSummary['items'],
            'membership_commission_amount' => $membershipCommissionSummary['amount'],
            'membership_commission_items' => $membershipCommissionSummary['items'],
            'manual_deductions' => $manualDeductions,
            'cash_advance_deduction' => $cashAdvanceDeduction,
            'net_amount' => $payrollTotals['net_amount'],
            'status' => Payroll::STATUS_DRAFT,
            'notes' => $data['notes'] ?? null,
            'generated_by' => auth()->id(),
        ]);

        $this->payrollService->syncPtCommissions($payroll);
        $this->payrollService->syncMembershipCommissions($payroll);
        $payroll = $payroll->fresh(['employee:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PAYROLL,
            $payroll->id,
            'created',
            $this->payrollAuditSnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializePayroll($payroll), 201);
    }

    public function updatePayroll(Request $request, User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be edited.'], 422);
        }

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
            'cash_advance_deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $businessProfile = BusinessProfile::current();
        $countryCode = $businessProfile->country_code;
        $attendanceSuggestion = $this->payrollService->suggestFromAttendance(
            $employee,
            $data['period_start'],
            $data['period_end'],
            (bool) $businessProfile->pay_overwork_hours,
        );
        $gross = (float) $data['gross_amount'];
        $bonus = (float) ($data['bonus'] ?? 0);
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceDeduction = (float) ($data['cash_advance_deduction'] ?? 0);
        $payFrequency = $this->employeePayFrequency($employee);
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'exclude_payroll_id' => $payroll->id,
            'period_end' => $data['period_end'],
        ];

        $ptCommissionSummary = $this->payrollService->previewPtCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
        );
        $membershipCommissionSummary = $this->payrollService->previewMembershipCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
        );
        $maxCashAdvanceDeduction = $this->payrollService->maxCashAdvanceDeduction(
            $employee->id,
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );
        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $cashAdvanceDeduction,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );

        if ($cashAdvanceDeduction > $maxCashAdvanceDeduction) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'cash_advance_deduction' => ["Cash advance deduction may not exceed {$maxCashAdvanceDeduction}."],
                ],
            ], 422);
        }

        $payroll->update([
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'pay_frequency' => $payFrequency,
            'regular_hours' => $attendanceSuggestion['regular_hours'],
            'regular_pay_amount' => $attendanceSuggestion['regular_pay_amount'],
            'overwork_hours' => $attendanceSuggestion['overwork_hours'],
            'overwork_pay_amount' => $attendanceSuggestion['overwork_pay_amount'],
            'gross_amount' => $gross,
            'bonus' => $bonus,
            'income_tax' => $payrollTotals['income_tax'],
            'pt_commission_amount' => $ptCommissionSummary['amount'],
            'pt_commission_items' => $ptCommissionSummary['items'],
            'membership_commission_amount' => $membershipCommissionSummary['amount'],
            'membership_commission_items' => $membershipCommissionSummary['items'],
            'manual_deductions' => $manualDeductions,
            'cash_advance_deduction' => $cashAdvanceDeduction,
            'net_amount' => $payrollTotals['net_amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->payrollService->syncPtCommissions($payroll);
        $this->payrollService->syncMembershipCommissions($payroll);
        $payroll = $payroll->fresh(['employee:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PAYROLL,
            $payroll->id,
            'updated',
            $this->payrollAuditSnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializePayroll($payroll));
    }

    public function approvePayroll(User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be approved.'], 422);
        }

        $payroll->status = Payroll::STATUS_APPROVED;
        $payroll->approved_by = auth()->id();
        $payroll->approved_at = now();
        $payroll->save();

        $this->payrollService->normalizePayrollCashAdvanceDeduction($payroll);
        $this->payrollService->applyAdvances($payroll);
        $this->payrollService->syncStatus($payroll);

        $this->notificationRecipientResolver->send(
            new PayrollApprovedNotification($payroll, $employee),
        );
        $payroll = $payroll->fresh(['employee:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PAYROLL,
            $payroll->id,
            'approved',
            $this->payrollAuditSnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            $payroll->approved_at ?? now(),
        );

        return response()->json($this->serializePayroll($payroll));
    }

    public function cancelPayroll(User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be canceled.'], 422);
        }

        $this->payrollService->releasePtCommissions($payroll);
        $this->payrollService->releaseMembershipCommissions($payroll);
        $payroll->status = Payroll::STATUS_CANCELED;
        $payroll->save();
        $payroll = $payroll->fresh(['employee:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PAYROLL,
            $payroll->id,
            'cancelled',
            $this->payrollAuditSnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializePayroll($payroll));
    }

    public function payrollSuggestedCa(User $employee): JsonResponse
    {
        return response()->json([
            'suggested_ca' => $this->payrollService->pendingCaTotal($employee->id),
        ]);
    }

    public function payrollSuggest(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'payroll_id' => ['nullable', 'integer', 'exists:payrolls,id'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
            'cash_advance_deduction' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payroll = null;

        if (! empty($data['payroll_id'])) {
            $payroll = Payroll::find($data['payroll_id']);

            if ($payroll && $payroll->employee_id !== $employee->id) {
                abort(404);
            }
        }

        $businessProfile = BusinessProfile::current();
        $countryCode = $businessProfile->country_code;
        $ptCommissionSummary = $this->payrollService->previewPtCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
        );
        $membershipCommissionSummary = $this->payrollService->previewMembershipCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
        );
        $attendanceSuggestion = $this->payrollService->suggestFromAttendance(
            $employee,
            $data['period_start'],
            $data['period_end'],
            (bool) $businessProfile->pay_overwork_hours,
        );
        $payFrequency = $payroll?->pay_frequency ?? $this->employeePayFrequency($employee);
        $grossAmount = (float) ($data['gross_amount'] ?? $attendanceSuggestion['gross_amount']);
        $bonusAmount = (float) ($data['bonus'] ?? 0);
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceDeduction = (float) ($data['cash_advance_deduction'] ?? 0);
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'period_end' => $data['period_end'],
        ];

        if ($payroll) {
            $payrollTaxContext['exclude_payroll_id'] = $payroll->id;
        }

        $maxCashAdvanceDeduction = $this->payrollService->maxCashAdvanceDeduction(
            $employee->id,
            $countryCode,
            $payFrequency,
            $grossAmount,
            $bonusAmount,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );
        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $grossAmount,
            $bonusAmount,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $cashAdvanceDeduction,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );

        return response()->json(array_merge(
            $attendanceSuggestion,
            [
                'pt_commission_amount' => $ptCommissionSummary['amount'],
                'pt_commission_items' => $ptCommissionSummary['items'],
                'membership_commission_amount' => $membershipCommissionSummary['amount'],
                'membership_commission_items' => $membershipCommissionSummary['items'],
                'bonus_non_taxable_amount' => $payrollTotals['bonus_non_taxable_amount'],
                'bonus_taxable_amount' => $payrollTotals['bonus_taxable_amount'],
                'income_tax' => $payrollTotals['income_tax'],
                'taxable_earnings' => $payrollTotals['taxable_earnings'],
                'employee_deductions_total' => $payrollTotals['employee_deductions_total'],
                'net_amount_preview' => $payrollTotals['net_amount'],
                'remaining_bonus_exemption' => $payrollTotals['remaining_bonus_exemption'],
                'max_cash_advance_deduction' => $maxCashAdvanceDeduction,
                'pending_ca_total' => $attendanceSuggestion['suggested_ca'],
                'suggested_ca' => min($attendanceSuggestion['suggested_ca'], $maxCashAdvanceDeduction),
                'manual_gross_adjustment_amount' => $this->payrollService->manualGrossAdjustmentAmount(
                    $grossAmount,
                    $attendanceSuggestion['regular_pay_amount'],
                    $attendanceSuggestion['overwork_pay_amount'],
                ),
            ]
        ));
    }

    public function payouts(User $employee): JsonResponse
    {
        $payouts = Payout::query()
            ->where('employee_id', $employee->id)
            ->with(['payroll:id,period_start,period_end', 'releasedBy:id,name'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (Payout $payout) => $this->serializePayout($payout))
            ->values()
            ->all();

        return response()->json($payouts);
    }

    public function payrollPayouts(User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        $payouts = $payroll->payouts()
            ->with(['payroll:id,period_start,period_end', 'releasedBy:id,name'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (Payout $payout) => $this->serializePayout($payout))
            ->values()
            ->all();

        return response()->json($payouts);
    }

    public function storePayout(Request $request, User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if (! in_array($payroll->status, [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID], true)) {
            return response()->json(['message' => 'Payroll must be approved before adding a payout.'], 422);
        }

        $remaining = $payroll->remainingBalance();

        if ($remaining <= 0) {
            return response()->json(['message' => 'Payroll has no remaining balance for payout.'], 422);
        }

        $data = $request->validate([
            'amount' => "required|numeric|min:0.01|max:{$remaining}",
            'method' => ['required', Rule::in([Payout::METHOD_CASH, Payout::METHOD_BANK_TRANSFER, Payout::METHOD_ONLINE_PAYMENT])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $payout = $payroll->payouts()->create([
            'employee_id' => $employee->id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference_number' => $data['reference_number'] ?? null,
            'released_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
        ]);

        $this->payrollService->syncStatus($payroll);
        $this->cashLedgerService->syncPayout($payout);
        $payout = $payout->fresh(['payroll', 'releasedBy', 'employee:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_PAYOUT,
            $payout->id,
            'created',
            $this->payoutAuditSnapshot($payout),
            [],
            auth()->id(),
            auth()->user()?->name,
            $payout->paid_at ?? now(),
        );

        return response()->json($this->serializePayout($payout), 201);
    }

    public function cashAdvances(User $employee): JsonResponse
    {
        $advances = CashAdvance::query()
            ->where('employee_id', $employee->id)
            ->with(['approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn (CashAdvance $cashAdvance) => $this->serializeCashAdvance($cashAdvance))
            ->values()
            ->all();

        $statsQuery = CashAdvance::where('employee_id', $employee->id);
        $remainingQuery = CashAdvance::where('employee_id', $employee->id)
            ->whereIn('status', [
                CashAdvance::STATUS_RELEASED,
                CashAdvance::STATUS_PARTIALLY_PAID,
            ]);

        return response()->json([
            'advances' => $advances,
            'stats' => [
                'remaining_amount' => (float) (clone $remainingQuery)->sum('remaining_amount'),
                'requested_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_REQUESTED)->count(),
                'approved_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_APPROVED)->count(),
                'released_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_RELEASED)->count(),
                'partially_paid_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_PARTIALLY_PAID)->count(),
                'paid_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_PAID)->count(),
            ],
        ]);
    }

    public function storeCashAdvance(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
            'requested_at' => ['nullable', 'date'],
        ]);

        $cashAdvance = CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => $data['amount'],
            'remaining_amount' => $data['amount'],
            'status' => CashAdvance::STATUS_REQUESTED,
            'notes' => $data['notes'] ?? null,
            'requested_at' => $data['requested_at'] ?? now(),
        ]);

        $this->recordCashAdvanceAuditEvent(
            $cashAdvance,
            $employee,
            CashAdvance::STATUS_REQUESTED,
            [
                'notes' => $cashAdvance->notes,
                'source' => 'panel',
            ],
            $cashAdvance->requested_at,
        );

        $this->notificationRecipientResolver->send(
            new CashAdvanceStatusChangedNotification($cashAdvance, $employee),
        );

        return response()->json($this->serializeCashAdvance($cashAdvance), 201);
    }

    public function updateCashAdvance(Request $request, User $employee, CashAdvance $cashAdvance): JsonResponse
    {
        abort_if($cashAdvance->employee_id !== $employee->id, 404);

        if (in_array($cashAdvance->status, [
            CashAdvance::STATUS_RELEASED,
            CashAdvance::STATUS_PARTIALLY_PAID,
            CashAdvance::STATUS_PAID,
            CashAdvance::STATUS_CANCELLED,
        ], true)) {
            return response()->json(['message' => 'Released and finalized cash advances cannot be edited here.'], 422);
        }

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:1'],
            'status' => [
                'sometimes',
                Rule::in([
                    CashAdvance::STATUS_REQUESTED,
                    CashAdvance::STATUS_APPROVED,
                    CashAdvance::STATUS_RELEASED,
                    CashAdvance::STATUS_CANCELLED,
                ]),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'requested_at' => ['nullable', 'date'],
        ]);

        $isEditingApprovedAdvance = $cashAdvance->status === CashAdvance::STATUS_APPROVED
            && (array_key_exists('amount', $data)
                || array_key_exists('notes', $data)
                || array_key_exists('requested_at', $data));

        if ($isEditingApprovedAdvance) {
            return response()->json([
                'message' => 'Approved cash advances cannot be edited.',
            ], 422);
        }

        $allowedTransitions = match ($cashAdvance->status) {
            CashAdvance::STATUS_REQUESTED => [
                CashAdvance::STATUS_REQUESTED,
                CashAdvance::STATUS_APPROVED,
                CashAdvance::STATUS_CANCELLED,
            ],
            CashAdvance::STATUS_APPROVED => [
                CashAdvance::STATUS_APPROVED,
                CashAdvance::STATUS_RELEASED,
                CashAdvance::STATUS_CANCELLED,
            ],
            default => [],
        };

        $nextStatus = $data['status'] ?? $cashAdvance->status;

        if (! in_array($nextStatus, $allowedTransitions, true)) {
            return response()->json(['message' => 'Invalid cash advance status transition.'], 422);
        }

        $previousStatus = $cashAdvance->status;
        $actor = auth()->user();

        $cashAdvance->update($data);

        if ($previousStatus !== $cashAdvance->status) {
            if ($cashAdvance->status === CashAdvance::STATUS_APPROVED && ! $cashAdvance->approved_at) {
                $cashAdvance->approved_at = now();
                $cashAdvance->approved_by = auth()->id();
            }

            if ($cashAdvance->status === CashAdvance::STATUS_RELEASED && ! $cashAdvance->released_at) {
                $cashAdvance->released_at = now();
                $cashAdvance->released_by = auth()->id();
            }

            if ($cashAdvance->status === CashAdvance::STATUS_CANCELLED && ! $cashAdvance->cancelled_at) {
                $cashAdvance->cancelled_at = now();
                $cashAdvance->cancelled_by = auth()->id();
            }

            $eventAt = match ($cashAdvance->status) {
                CashAdvance::STATUS_APPROVED => $cashAdvance->approved_at,
                CashAdvance::STATUS_RELEASED => $cashAdvance->released_at,
                CashAdvance::STATUS_CANCELLED => $cashAdvance->cancelled_at,
                default => now(),
            };

            $this->recordCashAdvanceAuditEvent(
                $cashAdvance,
                $employee,
                $cashAdvance->status,
                [
                    'notes' => $cashAdvance->notes,
                    'source' => 'panel',
                ],
                $eventAt,
                $actor?->id,
                $actor?->name,
            );
        }

        $cashAdvance->remaining_amount = (float) $cashAdvance->amount;
        $cashAdvance->save();
        $cashAdvance->load(['approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name']);
        $this->cashLedgerService->syncCashAdvance($cashAdvance, $cashAdvance->status);

        if ($previousStatus !== $cashAdvance->status) {
            $this->notificationRecipientResolver->send(
                new CashAdvanceStatusChangedNotification($cashAdvance, $employee),
            );
        }

        return response()->json($this->serializeCashAdvance($cashAdvance));
    }

    public function destroyCashAdvance(User $employee, CashAdvance $cashAdvance): JsonResponse
    {
        abort_if($cashAdvance->employee_id !== $employee->id, 404);

        if (in_array($cashAdvance->status, [
            CashAdvance::STATUS_RELEASED,
            CashAdvance::STATUS_PARTIALLY_PAID,
            CashAdvance::STATUS_PAID,
            CashAdvance::STATUS_CANCELLED,
        ], true)) {
            return response()->json(['message' => 'Finalized cash advances cannot be deleted.'], 422);
        }

        $this->recordCashAdvanceAuditEvent(
            $cashAdvance,
            $employee,
            'deleted',
            [
                'notes' => $cashAdvance->notes,
                'source' => 'panel',
            ],
            now(),
            auth()->id(),
            auth()->user()?->name,
        );
        $this->cashLedgerService->syncCashAdvance($cashAdvance, 'deleted');
        $cashAdvance->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEmployee(User $employee): array
    {
        $employee->loadMissing(['roles', 'employeeProfile']);

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'status' => $employee->status,
            'roles' => $employee->roles
                ->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ])
                ->values()
                ->all(),
            'employee_profile' => $this->serializeEmployeeProfile($employee->employeeProfile),
            'location' => BusinessProfile::current()->locationSummary(),
        ];
    }

    /**
     * @param  array{daily_rate?: float|int|string|null, pay_frequency?: string|null}  $attributes
     */
    private function ensureEmployeeProfile(User $employee, array $attributes = []): EmployeeProfile
    {
        $profile = EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $employee->id],
            [
                'hikvision_employee_no' => $this->defaultHikvisionEmployeeNo($employee->id),
                'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
            ],
        );

        if (trim((string) $profile->hikvision_employee_no) === '' || $profile->hikvision_employee_no === 'EMP-'.$employee->id) {
            $profile->update([
                'hikvision_employee_no' => $this->defaultHikvisionEmployeeNo($employee->id),
            ]);
        }

        if ($attributes !== []) {
            $profile->fill([
                'daily_rate' => $attributes['daily_rate'] ?? $profile->daily_rate,
                'pay_frequency' => $attributes['pay_frequency'] ?? $profile->pay_frequency,
            ]);

            if ($profile->isDirty(['daily_rate', 'pay_frequency'])) {
                $profile->save();
            }
        }

        return $profile->fresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeEmployeeProfile(?EmployeeProfile $employeeProfile): ?array
    {
        if (! $employeeProfile) {
            return null;
        }

        return [
            'id' => $employeeProfile->id,
            'daily_rate' => round((float) ($employeeProfile->daily_rate ?? 0), 2),
            'pay_frequency' => $employeeProfile->pay_frequency,
            'hikvision_employee_no' => $employeeProfile->hikvision_employee_no,
            'biometric_status' => $employeeProfile->biometric_status,
            'biometric_fingerprint_id' => $employeeProfile->biometric_fingerprint_id,
            'biometric_enrolled_at' => $employeeProfile->biometric_enrolled_at?->toISOString(),
            'biometric_last_error' => $employeeProfile->biometric_last_error,
        ];
    }

    private function defaultHikvisionEmployeeNo(int $employeeId): string
    {
        return str_pad((string) $employeeId, 8, '0', STR_PAD_LEFT);
    }

    private function employeeDailyRate(User $employee): float
    {
        $employee->loadMissing('employeeProfile');

        return round((float) ($employee->employeeProfile?->daily_rate ?? 0), 2);
    }

    private function employeePayFrequency(User $employee): ?string
    {
        $employee->loadMissing('employeeProfile');

        return $employee->employeeProfile?->pay_frequency;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayroll(Payroll $payroll): array
    {
        $payroll->loadMissing(['payouts', 'approvedBy:id,name']);
        $totalPaid = (float) $payroll->payouts->sum('amount');
        $manualGrossAdjustmentAmount = $payroll->hasAttendanceBreakdownSnapshot()
            ? $this->payrollService->manualGrossAdjustmentAmount(
                (float) $payroll->gross_amount,
                (float) $payroll->regular_pay_amount,
                (float) $payroll->overwork_pay_amount,
            )
            : null;

        return [
            'id' => $payroll->id,
            'period_start' => $payroll->period_start->format('Y-m-d'),
            'period_end' => $payroll->period_end->format('Y-m-d'),
            'regular_hours' => $payroll->regular_hours !== null ? (float) $payroll->regular_hours : null,
            'regular_pay_amount' => $payroll->regular_pay_amount !== null ? (float) $payroll->regular_pay_amount : null,
            'overwork_hours' => $payroll->overwork_hours !== null ? (float) $payroll->overwork_hours : null,
            'overwork_pay_amount' => $payroll->overwork_pay_amount !== null ? (float) $payroll->overwork_pay_amount : null,
            'manual_gross_adjustment_amount' => $manualGrossAdjustmentAmount,
            'gross_amount' => (float) $payroll->gross_amount,
            'bonus' => (float) $payroll->bonus,
            'pay_frequency' => $payroll->pay_frequency,
            'pt_commission_amount' => (float) $payroll->pt_commission_amount,
            'pt_commission_items' => $payroll->pt_commission_items ?? [],
            'membership_commission_amount' => (float) $payroll->membership_commission_amount,
            'membership_commission_items' => $payroll->membership_commission_items ?? [],
            'employee_deductions_total' => $payroll->employeeDeductionsTotal(),
            'total_earnings' => $payroll->totalEarnings(),
            'income_tax' => (float) $payroll->income_tax,
            'manual_deductions' => (float) $payroll->manual_deductions,
            'cash_advance_deduction' => (float) $payroll->cash_advance_deduction,
            'net_amount' => (float) $payroll->net_amount,
            'status' => $payroll->status,
            'notes' => $payroll->notes,
            'total_paid' => $totalPaid,
            'remaining_balance' => max(0, (float) $payroll->net_amount - $totalPaid),
            'payouts_count' => $payroll->payouts->count(),
            'approved_by_name' => $payroll->approvedBy?->name,
            'approved_at' => $payroll->approved_at?->toISOString(),
            'created_at' => $payroll->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayout(Payout $payout): array
    {
        $payout->loadMissing(['payroll:id,period_start,period_end', 'releasedBy:id,name']);

        return [
            'id' => $payout->id,
            'payroll_id' => $payout->payroll_id,
            'amount' => (float) $payout->amount,
            'method' => $payout->method,
            'reference_number' => $payout->reference_number,
            'notes' => $payout->notes,
            'paid_at' => $payout->paid_at?->toISOString(),
            'released_by_name' => $payout->releasedBy?->name,
            'payroll_period' => $payout->payroll
                ? $payout->payroll->period_start->format('Y-m-d').' – '.$payout->payroll->period_end->format('Y-m-d')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCashAdvance(CashAdvance $cashAdvance): array
    {
        $cashAdvance->loadMissing(['approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name']);

        return [
            'id' => $cashAdvance->id,
            'amount' => (float) $cashAdvance->amount,
            'remaining_amount' => (float) $cashAdvance->remaining_amount,
            'deducted_amount' => round((float) $cashAdvance->amount - (float) $cashAdvance->remaining_amount, 2),
            'status' => $cashAdvance->status,
            'notes' => $cashAdvance->notes,
            'requested_at' => $cashAdvance->requested_at?->toISOString(),
            'approved_at' => $cashAdvance->approved_at?->toISOString(),
            'approved_by_name' => $cashAdvance->approvedBy?->name,
            'released_at' => $cashAdvance->released_at?->toISOString(),
            'released_by_name' => $cashAdvance->releasedBy?->name,
            'cancelled_at' => $cashAdvance->cancelled_at?->toISOString(),
            'cancelled_by_name' => $cashAdvance->cancelledBy?->name,
            'paid_at' => $cashAdvance->paid_at?->toISOString(),
            'created_at' => $cashAdvance->created_at?->toISOString(),
        ];
    }

    private function recordCashAdvanceAuditEvent(
        CashAdvance $cashAdvance,
        User $employee,
        string $event,
        array $metadata,
        mixed $occurredAt,
        ?int $actorUserId = null,
        ?string $actorName = null,
    ): void {
        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_ADVANCE,
            $cashAdvance->id,
            $event,
            [
                'id' => $cashAdvance->id,
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'amount' => round((float) $cashAdvance->amount, 2),
            ],
            $metadata,
            $actorUserId ?? auth()->id(),
            $actorName ?? auth()->user()?->name,
            $occurredAt ?? now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeAuditSnapshot(User $employee): array
    {
        $employee->loadMissing(['roles', 'employeeProfile']);

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'status' => $employee->status,
            'role_names' => $employee->roles->pluck('name')->values()->all(),
            'daily_rate' => $this->employeeDailyRate($employee),
            'pay_frequency' => $this->employeePayFrequency($employee),
            'biometric_status' => $employee->employeeProfile?->biometric_status,
            'biometric_fingerprint_id' => $employee->employeeProfile?->biometric_fingerprint_id,
            'biometric_enrolled_at' => $employee->employeeProfile?->biometric_enrolled_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollAuditSnapshot(Payroll $payroll, User $employee): array
    {
        return [
            'id' => $payroll->id,
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'period_start' => $payroll->period_start?->format('Y-m-d'),
            'period_end' => $payroll->period_end?->format('Y-m-d'),
            'regular_hours' => round((float) $payroll->regular_hours, 2),
            'regular_pay_amount' => round((float) $payroll->regular_pay_amount, 2),
            'overwork_hours' => round((float) $payroll->overwork_hours, 2),
            'overwork_pay_amount' => round((float) $payroll->overwork_pay_amount, 2),
            'net_amount' => round((float) $payroll->net_amount, 2),
            'status' => $payroll->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutAuditSnapshot(Payout $payout): array
    {
        $payrollPeriod = $payout->payroll && $payout->payroll->period_start && $payout->payroll->period_end
            ? $payout->payroll->period_start->format('Y-m-d').' - '.$payout->payroll->period_end->format('Y-m-d')
            : null;

        return [
            'id' => $payout->id,
            'employee_id' => $payout->employee_id,
            'employee_name' => $payout->employee?->name ?? 'Unknown Employee',
            'payroll_id' => $payout->payroll_id,
            'payroll_period' => $payrollPeriod,
            'amount' => round((float) $payout->amount, 2),
            'method' => $payout->method,
        ];
    }
}
