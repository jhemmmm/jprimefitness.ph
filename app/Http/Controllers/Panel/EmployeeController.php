<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditEvent;
use App\Models\BusinessProfile;
use App\Models\EmployeeProfile;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use App\Notifications\PayrollApprovedNotification;
use App\Services\AuditHistoryService;
use App\Services\NotificationRecipientResolver;
use App\Services\PayrollService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class EmployeeController extends Controller
{
    public function __construct(
        private PayrollService $payrollService,
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
        $businessProfile = BusinessProfile::current();
        $isPhilippinesBusiness = $this->isPhilippinesPayrollBusiness($businessProfile);

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
            'employee_profile.sss_covered' => [Rule::requiredIf($isPhilippinesBusiness), 'boolean'],
            'employee_profile.sss_monthly_compensation' => ['nullable', 'numeric', 'min:0'],
            'employee_profile.philhealth_covered' => [Rule::requiredIf($isPhilippinesBusiness), 'boolean'],
            'employee_profile.philhealth_monthly_basic_salary' => ['nullable', 'numeric', 'min:0'],
            'employee_profile.pagibig_covered' => [Rule::requiredIf($isPhilippinesBusiness), 'boolean'],
            'employee_profile.pagibig_monthly_compensation' => ['nullable', 'numeric', 'min:0'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        $data['employee_profile'] = $this->normalizeEmployeeProfileAttributes(
            $data['employee_profile'] ?? [],
            $isPhilippinesBusiness
        );

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
        $businessProfile = BusinessProfile::current();
        $isPhilippinesBusiness = $this->isPhilippinesPayrollBusiness($businessProfile);

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
            'employee_profile.sss_covered' => [Rule::requiredIf($isPhilippinesBusiness), 'boolean'],
            'employee_profile.sss_monthly_compensation' => ['nullable', 'numeric', 'min:0'],
            'employee_profile.philhealth_covered' => [Rule::requiredIf($isPhilippinesBusiness), 'boolean'],
            'employee_profile.philhealth_monthly_basic_salary' => ['nullable', 'numeric', 'min:0'],
            'employee_profile.pagibig_covered' => [Rule::requiredIf($isPhilippinesBusiness), 'boolean'],
            'employee_profile.pagibig_monthly_compensation' => ['nullable', 'numeric', 'min:0'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);
        $data['employee_profile'] = $this->normalizeEmployeeProfileAttributes(
            $data['employee_profile'] ?? [],
            $isPhilippinesBusiness
        );

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
        $employee->loadMissing('employeeProfile');

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
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
        $payFrequency = $this->employeePayFrequency($employee);
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'employee_profile' => $this->serializeEmployeeProfile($employee->employeeProfile),
            'business_profile' => $this->serializeBusinessProfilePayrollSettings($businessProfile),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ];

        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $manualDeductions,
            $payrollTaxContext
        );

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
            'employee_contributions' => $payrollTotals['employee_contributions'],
            'employer_contributions' => $payrollTotals['employer_contributions'],
            'manual_deductions' => $manualDeductions,
            'net_amount' => $payrollTotals['net_amount'],
            'status' => Payroll::STATUS_DRAFT,
            'notes' => $data['notes'] ?? null,
            'generated_by' => auth()->id(),
        ]);

        $this->payrollService->syncMonthlyGovernmentContributionAllocation($payroll);
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
        $employee->loadMissing('employeeProfile');

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be edited.'], 422);
        }

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
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
        $payFrequency = $this->employeePayFrequency($employee);
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'employee_profile' => $this->serializeEmployeeProfile($employee->employeeProfile),
            'business_profile' => $this->serializeBusinessProfilePayrollSettings($businessProfile),
            'payroll_id' => $payroll->id,
            'exclude_payroll_id' => $payroll->id,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ];

        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $gross,
            $bonus,
            $manualDeductions,
            $payrollTaxContext
        );

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
            'employee_contributions' => $payrollTotals['employee_contributions'],
            'employer_contributions' => $payrollTotals['employer_contributions'],
            'manual_deductions' => $manualDeductions,
            'net_amount' => $payrollTotals['net_amount'],
            'notes' => $data['notes'] ?? null,
        ]);

        $this->payrollService->syncMonthlyGovernmentContributionAllocation($payroll);
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

        $payroll->status = Payroll::STATUS_CANCELED;
        $payroll->save();
        $this->payrollService->syncMonthlyGovernmentContributionAllocation($payroll);
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

    public function payrollSuggest(Request $request, User $employee): JsonResponse
    {
        $employee->loadMissing('employeeProfile');

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'payroll_id' => ['nullable', 'integer', 'exists:payrolls,id'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
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
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'employee_profile' => $this->serializeEmployeeProfile($employee->employeeProfile),
            'business_profile' => $this->serializeBusinessProfilePayrollSettings($businessProfile),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ];

        if ($payroll) {
            $payrollTaxContext['payroll_id'] = $payroll->id;
            $payrollTaxContext['exclude_payroll_id'] = $payroll->id;
        }

        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $countryCode,
            $payFrequency,
            $grossAmount,
            $bonusAmount,
            $manualDeductions,
            $payrollTaxContext
        );

        return response()->json(array_merge(
            $attendanceSuggestion,
            [
                'bonus_non_taxable_amount' => $payrollTotals['bonus_non_taxable_amount'],
                'bonus_taxable_amount' => $payrollTotals['bonus_taxable_amount'],
                'employee_contributions' => $payrollTotals['employee_contributions'],
                'employee_contributions_total' => $payrollTotals['employee_contributions_total'],
                'employer_contributions' => $payrollTotals['employer_contributions'],
                'employer_contributions_total' => $payrollTotals['employer_contributions_total'],
                'income_tax' => $payrollTotals['income_tax'],
                'taxable_earnings' => $payrollTotals['taxable_earnings'],
                'employee_deductions_total' => $payrollTotals['employee_deductions_total'],
                'net_amount_preview' => $payrollTotals['net_amount'],
                'remaining_bonus_exemption' => $payrollTotals['remaining_bonus_exemption'],
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
     * @param  array{
     *     daily_rate?: float|int|string|null,
     *     pay_frequency?: string|null,
     *     sss_covered?: bool,
     *     sss_monthly_compensation?: float|int|string|null,
     *     philhealth_covered?: bool,
     *     philhealth_monthly_basic_salary?: float|int|string|null,
     *     pagibig_covered?: bool,
     *     pagibig_monthly_compensation?: float|int|string|null
     * }  $attributes
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
                'sss_covered' => $attributes['sss_covered'] ?? $profile->sss_covered,
                'sss_monthly_compensation' => array_key_exists('sss_monthly_compensation', $attributes)
                    ? $attributes['sss_monthly_compensation']
                    : $profile->sss_monthly_compensation,
                'philhealth_covered' => $attributes['philhealth_covered'] ?? $profile->philhealth_covered,
                'philhealth_monthly_basic_salary' => array_key_exists('philhealth_monthly_basic_salary', $attributes)
                    ? $attributes['philhealth_monthly_basic_salary']
                    : $profile->philhealth_monthly_basic_salary,
                'pagibig_covered' => $attributes['pagibig_covered'] ?? $profile->pagibig_covered,
                'pagibig_monthly_compensation' => array_key_exists('pagibig_monthly_compensation', $attributes)
                    ? $attributes['pagibig_monthly_compensation']
                    : $profile->pagibig_monthly_compensation,
            ]);

            if ($profile->isDirty([
                'daily_rate',
                'pay_frequency',
                'sss_covered',
                'sss_monthly_compensation',
                'philhealth_covered',
                'philhealth_monthly_basic_salary',
                'pagibig_covered',
                'pagibig_monthly_compensation',
            ])) {
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
            'sss_covered' => (bool) $employeeProfile->sss_covered,
            'sss_monthly_compensation' => $employeeProfile->sss_monthly_compensation !== null
                ? round((float) $employeeProfile->sss_monthly_compensation, 2)
                : null,
            'philhealth_covered' => (bool) $employeeProfile->philhealth_covered,
            'philhealth_monthly_basic_salary' => $employeeProfile->philhealth_monthly_basic_salary !== null
                ? round((float) $employeeProfile->philhealth_monthly_basic_salary, 2)
                : null,
            'pagibig_covered' => (bool) $employeeProfile->pagibig_covered,
            'pagibig_monthly_compensation' => $employeeProfile->pagibig_monthly_compensation !== null
                ? round((float) $employeeProfile->pagibig_monthly_compensation, 2)
                : null,
            'hikvision_employee_no' => $employeeProfile->hikvision_employee_no,
            'biometric_status' => $employeeProfile->biometric_status,
            'biometric_fingerprint_id' => $employeeProfile->biometric_fingerprint_id,
            'biometric_enrolled_at' => $employeeProfile->biometric_enrolled_at?->toISOString(),
            'biometric_last_error' => $employeeProfile->biometric_last_error,
        ];
    }

    /**
     * @return array{payroll_income_tax_enabled: bool, payroll_government_contributions_enabled: bool}
     */
    private function serializeBusinessProfilePayrollSettings(BusinessProfile $businessProfile): array
    {
        return [
            'payroll_income_tax_enabled' => (bool) $businessProfile->payroll_income_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $businessProfile->payroll_government_contributions_enabled,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeEmployeeProfileAttributes(array $attributes, bool $isPhilippinesBusiness): array
    {
        $normalized = [
            'daily_rate' => round((float) ($attributes['daily_rate'] ?? 0), 2),
            'pay_frequency' => $attributes['pay_frequency'] ?? null,
            'sss_covered' => $isPhilippinesBusiness ? (bool) ($attributes['sss_covered'] ?? false) : false,
            'sss_monthly_compensation' => $isPhilippinesBusiness
                ? $this->normalizeNullableMoney($attributes['sss_monthly_compensation'] ?? null)
                : null,
            'philhealth_covered' => $isPhilippinesBusiness ? (bool) ($attributes['philhealth_covered'] ?? false) : false,
            'philhealth_monthly_basic_salary' => $isPhilippinesBusiness
                ? $this->normalizeNullableMoney($attributes['philhealth_monthly_basic_salary'] ?? null)
                : null,
            'pagibig_covered' => $isPhilippinesBusiness ? (bool) ($attributes['pagibig_covered'] ?? false) : false,
            'pagibig_monthly_compensation' => $isPhilippinesBusiness
                ? $this->normalizeNullableMoney($attributes['pagibig_monthly_compensation'] ?? null)
                : null,
        ];

        if (! $isPhilippinesBusiness) {
            return $normalized;
        }

        $validationErrors = [];

        if ($normalized['sss_covered'] && ! $normalized['sss_monthly_compensation']) {
            $validationErrors['employee_profile.sss_monthly_compensation'] = [
                'SSS monthly compensation is required when SSS coverage is enabled.',
            ];
        }

        if ($normalized['philhealth_covered'] && ! $normalized['philhealth_monthly_basic_salary']) {
            $validationErrors['employee_profile.philhealth_monthly_basic_salary'] = [
                'PhilHealth monthly basic salary is required when PhilHealth coverage is enabled.',
            ];
        }

        if ($normalized['pagibig_covered'] && ! $normalized['pagibig_monthly_compensation']) {
            $validationErrors['employee_profile.pagibig_monthly_compensation'] = [
                'Pag-IBIG monthly compensation is required when Pag-IBIG coverage is enabled.',
            ];
        }

        if ($validationErrors !== []) {
            throw ValidationException::withMessages($validationErrors);
        }

        return $normalized;
    }

    private function normalizeNullableMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function isPhilippinesPayrollBusiness(?BusinessProfile $businessProfile = null): bool
    {
        return ($businessProfile ?? BusinessProfile::current())->country_code === BusinessProfile::COUNTRY_PHILIPPINES;
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
            'employee_contributions' => $payroll->employee_contributions ?? [],
            'employee_contributions_total' => $payroll->employeeContributionsTotal(),
            'employee_deductions_total' => $payroll->employeeDeductionsTotal(),
            'employer_contributions' => $payroll->employer_contributions ?? [],
            'employer_contributions_total' => $payroll->employerContributionsTotal(),
            'total_earnings' => $payroll->totalEarnings(),
            'income_tax' => (float) $payroll->income_tax,
            'manual_deductions' => (float) $payroll->manual_deductions,
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
            'sss_covered' => (bool) $employee->employeeProfile?->sss_covered,
            'sss_monthly_compensation' => round((float) ($employee->employeeProfile?->sss_monthly_compensation ?? 0), 2),
            'philhealth_covered' => (bool) $employee->employeeProfile?->philhealth_covered,
            'philhealth_monthly_basic_salary' => round((float) ($employee->employeeProfile?->philhealth_monthly_basic_salary ?? 0), 2),
            'pagibig_covered' => (bool) $employee->employeeProfile?->pagibig_covered,
            'pagibig_monthly_compensation' => round((float) ($employee->employeeProfile?->pagibig_monthly_compensation ?? 0), 2),
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
            'income_tax' => round((float) $payroll->income_tax, 2),
            'employee_contributions' => $payroll->employee_contributions ?? [],
            'employee_contributions_total' => $payroll->employeeContributionsTotal(),
            'employer_contributions' => $payroll->employer_contributions ?? [],
            'employer_contributions_total' => $payroll->employerContributionsTotal(),
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
