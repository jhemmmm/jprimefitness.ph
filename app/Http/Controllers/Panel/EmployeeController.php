<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\SystemActivity;
use App\Models\BusinessProfile;
use App\Models\EmployeeProfile;
use App\Models\EmployeeScheduleShift;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRepayment;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\SaleTransaction;
use App\Models\User;
use App\Notifications\PayrollApprovedNotification;
use App\Services\SystemActivityService;
use App\Services\NotificationRecipientResolver;
use App\Services\PayrollService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class EmployeeController extends Controller
{
    /**
     * Create a new employee controller instance.
     *
     * @return void
     */
    public function __construct(
        private PayrollService $payrollService,
        private NotificationRecipientResolver $notificationRecipientResolver,
        private SystemActivityService $systemActivityService,
    ) {
        $this->middleware('can:manage employees')->except([
            'show', 'schedule', 'attendance', 'payrolls', 'payslip', 'payouts', 'payrollPayouts', 'cashAdvances',
        ]);
    }

    /**
     * Display the employees page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index(): View
    {
        return view('panel.employees.index');
    }

    /**
     * Display an employee detail page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function show(User $employee): View
    {
        $this->authorizeSelfOrManager($employee);

        return view('panel.employees.show', [
            'employee' => $this->serializeEmployee($employee->load('roles')),
            'employeeName' => $employee->name,
        ]);
    }

    /**
     * Return employee records.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $roles = array_values(array_filter((array) $request->input('role', []), fn ($value) => $value !== null && $value !== ''));
        $statuses = array_values(array_filter((array) $request->input('status', []), fn ($value) => $value !== null && $value !== ''));

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
            ->when(! empty($roles), fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('id', $roles)))
            ->when(! empty($statuses), fn ($query) => $query->whereIn('status', $statuses))
            ->orderBy('name')
            ->get()
            ->map(fn (User $employee) => $this->serializeEmployee($employee))
            ->values()
            ->all();

        return response()->json($employees);
    }

    /**
     * Create an employee record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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
            'employee_profile.pt_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_EMPLOYEE,
            $employee->id,
            'created',
            $this->employeeSystemActivitySnapshot($employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializeEmployee($employee), 201);
    }

    /**
     * Update an employee record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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
            'employee_profile.pt_commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_EMPLOYEE,
            $employee->id,
            'updated',
            $this->employeeSystemActivitySnapshot($employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializeEmployee($employee));
    }

    /**
     * Delete an employee record.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(User $employee): JsonResponse
    {
        abort_if($employee->id === auth()->id(), 403);
        $snapshot = $this->employeeSystemActivitySnapshot($employee->loadMissing('roles'));

        $employee->delete();

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_EMPLOYEE,
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

    /**
     * Return the weekly schedule for an employee.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function schedule(User $employee): JsonResponse
    {
        $this->authorizeSelfOrManager($employee);

        $profile = $this->ensureEmployeeProfile($employee);
        $profile->load('scheduleShifts');

        return response()->json([
            'shifts' => $this->serializeScheduleShifts($profile),
        ]);
    }

    /**
     * Replace the weekly schedule for an employee.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSchedule(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'shifts' => ['present', 'array'],
            'shifts.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'shifts.*.start_time' => ['required', 'date_format:H:i'],
            'shifts.*.end_time' => ['required', 'date_format:H:i', 'after:shifts.*.start_time'],
        ]);

        $this->assertNoOverlap($data['shifts'] ?? []);

        $profile = $this->ensureEmployeeProfile($employee);

        DB::transaction(function () use ($profile, $data) {
            $profile->scheduleShifts()->delete();

            foreach ($data['shifts'] ?? [] as $shift) {
                $profile->scheduleShifts()->create([
                    'day_of_week' => (int) $shift['day_of_week'],
                    'start_time' => $shift['start_time'].':00',
                    'end_time' => $shift['end_time'].':00',
                ]);
            }
        });

        $profile->load('scheduleShifts');
        $shifts = $this->serializeScheduleShifts($profile);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_EMPLOYEE,
            $employee->id,
            'schedule_updated',
            ['shifts' => $shifts],
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json(['shifts' => $shifts]);
    }

    /**
     * Return employee attendance records.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function attendance(Request $request, User $employee): JsonResponse
    {
        $this->authorizeSelfOrManager($employee);

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

        $totalMinutes = (clone $statsQuery)
            ->whereNotNull('checked_in_at')
            ->whereNotNull('checked_out_at')
            ->get(['checked_in_at', 'checked_out_at'])
            ->sum(fn (Attendance $a) => max(0, $a->checked_in_at->diffInMinutes($a->checked_out_at)));

        return response()->json([
            'records' => $records,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'this_month' => (clone $statsQuery)->whereMonth('checked_in_at', now()->month)->whereYear('checked_in_at', now()->year)->count(),
                'currently_in' => (clone $statsQuery)->whereNull('checked_out_at')->count(),
                'total_hours' => round($totalMinutes / 60, 1),
            ],
        ]);
    }

    /**
     * Return employee payroll records.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function payrolls(User $employee): JsonResponse
    {
        $this->authorizeSelfOrManager($employee);

        $payrolls = Payroll::query()
            ->where('employee_id', $employee->id)
            ->with('approvedBy:id,name')
            ->withSum('payouts', 'amount')
            ->withCount('payouts')
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (Payroll $payroll) => $this->serializePayroll($payroll))
            ->values()
            ->all();

        return response()->json($payrolls);
    }

    /**
     * Download an employee payslip.
     *
     * @return \Illuminate\Contracts\Support\Responsable
     */
    public function payslip(User $employee, Payroll $payroll): Responsable
    {
        $this->authorizeSelfOrManager($employee);

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

    /**
     * Preview statutory contribution amounts for given profile inputs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function contributionPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pay_frequency' => ['nullable', Rule::in(['monthly', 'semi_monthly'])],
            'sss_covered' => ['sometimes', 'boolean'],
            'sss_monthly_compensation' => ['nullable', 'numeric', 'min:0'],
            'philhealth_covered' => ['sometimes', 'boolean'],
            'philhealth_monthly_basic_salary' => ['nullable', 'numeric', 'min:0'],
            'pagibig_covered' => ['sometimes', 'boolean'],
            'pagibig_monthly_compensation' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($this->payrollService->previewGovernmentContributions(
            $data,
            $data['pay_frequency'] ?? 'semi_monthly'
        ));
    }

    /**
     * Display the payroll create/edit document page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function createPayrollPage(Request $request, User $employee): View
    {
        $payroll = null;

        if ($request->filled('payroll')) {
            $payroll = Payroll::query()
                ->where('employee_id', $employee->id)
                ->findOrFail($request->integer('payroll'));

            abort_unless($payroll->status === Payroll::STATUS_DRAFT, 404);
        }

        return view('panel.employees.payroll-form', [
            'employee' => $this->serializeEmployee($employee->load('roles')),
            'employeeName' => $employee->name,
            'payroll' => $payroll ? $this->serializePayroll($payroll) : null,
        ]);
    }

    /**
     * Create an employee payroll.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storePayroll(Request $request, User $employee): JsonResponse
    {
        $authUser = auth()->user();
        abort_if(
            (int) $employee->id === (int) $authUser->id
                && $authUser->hasRole('manager')
                && ! $authUser->hasAnyRole(['super admin', 'admin']),
            403,
            'Managers cannot generate their own payroll.'
        );

        $employee->loadMissing('employeeProfile');

        $data = $this->validatePayrollInput($request, $employee);

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            ...$this->payrollAttributes($employee, $data),
            'status' => Payroll::STATUS_DRAFT,
            'generated_by' => auth()->id(),
        ]);

        $this->payrollService->syncMonthlyGovernmentContributionAllocation($payroll);
        $payroll = $payroll->fresh(['employee:id,name']);

        return response()->json($this->serializePayroll($payroll), 201);
    }

    /**
     * Update an employee payroll.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePayroll(Request $request, User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);
        $employee->loadMissing('employeeProfile');

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be edited.'], 422);
        }

        $data = $this->validatePayrollInput($request, $employee);

        $payroll->update($this->payrollAttributes($employee, $data, $payroll));

        $this->payrollService->syncMonthlyGovernmentContributionAllocation($payroll);
        $payroll = $payroll->fresh(['employee:id,name']);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_PAYROLL,
            $payroll->id,
            'updated',
            $this->payrollSystemActivitySnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializePayroll($payroll));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayrollInput(Request $request, User $employee): array
    {
        return $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'gross_amount' => ['required', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
            'cash_advance_deductions' => $request->filled('cash_advance_deductions')
                ? ['numeric', 'min:0', 'max:'.$this->cashAdvanceOutstanding($employee)]
                : ['nullable'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Build the shared payroll column set from validated input: attendance
     * suggestion, tax/contribution totals, and period metadata.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payrollAttributes(User $employee, array $data, ?Payroll $existingPayroll = null): array
    {
        $businessProfile = BusinessProfile::current();
        $attendanceSuggestion = $this->payrollService->suggestFromAttendance(
            $employee,
            $data['period_start'],
            $data['period_end'],
            (bool) $businessProfile->pay_overwork_hours,
        );
        $commission = $this->payrollService->suggestPtCommissions($employee, $data['period_start'], $data['period_end']);
        $gross = (float) $data['gross_amount'];
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceDeductions = (float) ($data['cash_advance_deductions'] ?? 0);
        $payFrequency = $this->employeePayFrequency($employee);
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'employee_profile' => $this->serializeEmployeeProfile($employee->employeeProfile),
            'business_profile' => $this->serializeBusinessProfilePayrollSettings($businessProfile),
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ];

        if ($existingPayroll) {
            $payrollTaxContext['payroll_id'] = $existingPayroll->id;
            $payrollTaxContext['exclude_payroll_id'] = $existingPayroll->id;
        }

        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $businessProfile->country_code,
            $payFrequency,
            $gross,
            $manualDeductions,
            $payrollTaxContext,
            $cashAdvanceDeductions
        );

        $availablePay = $this->availablePayForCashAdvance($gross, $manualDeductions, $payrollTotals);

        if ($cashAdvanceDeductions > $availablePay) {
            throw ValidationException::withMessages([
                'cash_advance_deductions' => 'Cash advance repayment cannot exceed the pay available for this period (₱'.number_format($availablePay, 2).').',
            ]);
        }

        return [
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'pay_frequency' => $payFrequency,
            'regular_hours' => $attendanceSuggestion['regular_hours'],
            'regular_pay_amount' => $attendanceSuggestion['regular_pay_amount'],
            'overwork_hours' => $attendanceSuggestion['overwork_hours'],
            'overwork_pay_amount' => $attendanceSuggestion['overwork_pay_amount'],
            'commission_amount' => $commission['amount'],
            'commission_details' => $commission['sales'],
            'gross_amount' => $gross,
            'withholding_tax' => $payrollTotals['withholding_tax'],
            'employee_contributions' => $payrollTotals['employee_contributions'],
            'employer_contributions' => $payrollTotals['employer_contributions'],
            'manual_deductions' => $manualDeductions,
            'cash_advance_deductions' => $cashAdvanceDeductions,
            'net_amount' => $payrollTotals['net_amount'],
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * Approve an employee payroll.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function approvePayroll(User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be approved.'], 422);
        }

        DB::transaction(function () use ($payroll) {
            $locked = Payroll::whereKey($payroll->getKey())->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== Payroll::STATUS_DRAFT, 422, 'Only draft payrolls can be approved.');

            $this->payrollService->assertPtCommissionSalesRemainEligible($locked);
            $this->applyCashAdvanceRepayments($locked);

            $locked->status = Payroll::STATUS_APPROVED;
            $locked->approved_by = auth()->id();
            $locked->approved_at = now();
            $locked->save();
        });

        $payroll->refresh();
        $this->payrollService->syncStatus($payroll);

        $this->notificationRecipientResolver->send(
            new PayrollApprovedNotification($payroll, $employee),
        );
        $payroll = $payroll->fresh(['employee:id,name']);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_PAYROLL,
            $payroll->id,
            'approved',
            $this->payrollSystemActivitySnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            $payroll->approved_at ?? now(),
        );

        return response()->json($this->serializePayroll($payroll));
    }

    /**
     * Cancel an employee payroll.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_PAYROLL,
            $payroll->id,
            'cancelled',
            $this->payrollSystemActivitySnapshot($payroll, $employee),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return response()->json($this->serializePayroll($payroll));
    }

    /**
     * Return payroll suggestions for an employee.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function payrollSuggest(Request $request, User $employee): JsonResponse
    {
        $employee->loadMissing('employeeProfile');

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'payroll_id' => ['nullable', 'integer', 'exists:payrolls,id'],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'manual_deductions' => ['nullable', 'numeric', 'min:0'],
            'cash_advance_deductions' => ['nullable', 'numeric', 'min:0'],
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
        $commission = $this->payrollService->suggestPtCommissions($employee, $data['period_start'], $data['period_end']);
        $suggestedGross = round((float) $attendanceSuggestion['gross_amount'] + $commission['amount'], 2);
        $grossAmount = (float) ($data['gross_amount'] ?? $suggestedGross);
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceOutstanding = $this->cashAdvanceOutstanding($employee);
        $cashAdvanceDeductions = (float) ($data['cash_advance_deductions'] ?? 0);
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
            $manualDeductions,
            $payrollTaxContext,
            $cashAdvanceDeductions
        );

        return response()->json(array_merge(
            $attendanceSuggestion,
            [
                'gross_amount' => $suggestedGross,
                'pt_commission_amount' => $commission['amount'],
                'pt_commission_sales' => $commission['sales'],
                'employee_contributions' => $payrollTotals['employee_contributions'],
                'employee_contributions_total' => $payrollTotals['employee_contributions_total'],
                'employer_contributions' => $payrollTotals['employer_contributions'],
                'employer_contributions_total' => $payrollTotals['employer_contributions_total'],
                'withholding_tax' => $payrollTotals['withholding_tax'],
                'taxable_earnings' => $payrollTotals['taxable_earnings'],
                'employee_deductions_total' => $payrollTotals['employee_deductions_total'],
                'cash_advance_outstanding' => $cashAdvanceOutstanding,
                'cash_advance_suggested' => min(
                    $cashAdvanceOutstanding,
                    $this->availablePayForCashAdvance($grossAmount, $manualDeductions, $payrollTotals)
                ),
                'net_amount_preview' => $payrollTotals['net_amount'],
                'manual_gross_adjustment_amount' => $this->payrollService->manualGrossAdjustmentAmount(
                    $grossAmount,
                    $attendanceSuggestion['regular_pay_amount'],
                    $attendanceSuggestion['overwork_pay_amount'],
                    $commission['amount'],
                ),
            ]
        ));
    }

    /**
     * Return released employee payouts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function payouts(User $employee): JsonResponse
    {
        $this->authorizeSelfOrManager($employee);

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

    /**
     * Return payouts for an employee payroll.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function payrollPayouts(User $employee, Payroll $payroll): JsonResponse
    {
        $this->authorizeSelfOrManager($employee);

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

    /**
     * Create a payroll payout.
     *
     * @return \Illuminate\Http\JsonResponse
     */
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

        return response()->json($this->serializePayout($payout), 201);
    }

    /**
     * Return employee cash advances.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cashAdvances(User $employee): JsonResponse
    {
        $this->authorizeSelfOrManager($employee);

        $advances = CashAdvance::query()
            ->where('employee_id', $employee->id)
            ->with(['releasedBy:id,name', 'voidedBy:id,name'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (CashAdvance $advance) => $this->serializeCashAdvance($advance))
            ->values()
            ->all();

        return response()->json($advances);
    }

    /**
     * Record an employee cash advance.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeCashAdvance(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in([CashAdvance::METHOD_CASH, CashAdvance::METHOD_GCASH, CashAdvance::METHOD_ONLINE_PAYMENT])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $advance = CashAdvance::create([
            'employee_id' => $employee->id,
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference_number' => $data['reference_number'] ?? null,
            'released_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
        ]);

        $advance->loadMissing('releasedBy:id,name');

        return response()->json($this->serializeCashAdvance($advance), 201);
    }

    /**
     * Void a cash advance recorded in error. Blocked once any payroll has
     * repaid against it — that repayment is baked into an approved
     * payroll's net pay and can't be undone by voiding the source advance.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function voidCashAdvance(Request $request, User $employee, CashAdvance $cashAdvance): JsonResponse
    {
        abort_if($cashAdvance->employee_id !== $employee->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $voided = DB::transaction(function () use ($cashAdvance, $employee, $data) {
            $locked = CashAdvance::whereKey($cashAdvance->getKey())->with('releasedBy:id,name')->lockForUpdate()->firstOrFail();

            abort_if($locked->isVoided(), 409, 'This cash advance has already been voided.');
            abort_if((float) $locked->repaid_amount > 0, 422, 'This cash advance has already been repaid through payroll and can no longer be voided.');

            $locked->setRelation('employee', $employee);
            $locked->setRelation('voidedBy', auth()->user());
            $locked->forceFill([
                'void_reason' => trim($data['reason']),
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ])->save();

            return $locked;
        });

        return response()->json($this->serializeCashAdvance($voided));
    }

    /**
     * Sum of unpaid cash advance balances for an employee.
     */
    private function cashAdvanceOutstanding(User $employee): float
    {
        return round((float) CashAdvance::query()
            ->where('employee_id', $employee->id)
            ->outstanding()
            ->sum(DB::raw('amount - repaid_amount')), 2);
    }

    /**
     * Net pay left to withhold an advance from: gross minus the other
     * employee deductions, before the advance deduction itself.
     *
     * @param  array<string, mixed>  $payrollTotals
     */
    private function availablePayForCashAdvance(float $gross, float $manualDeductions, array $payrollTotals): float
    {
        return max(0, round(
            $gross
            - $manualDeductions
            - (float) $payrollTotals['withholding_tax']
            - (float) $payrollTotals['employee_contributions_total'],
            2
        ));
    }

    /**
     * Allocate a payroll's cash advance deduction across outstanding
     * advances, oldest first. Aborts if balances shrank since drafting.
     */
    private function applyCashAdvanceRepayments(Payroll $payroll): void
    {
        $remaining = round((float) $payroll->cash_advance_deductions, 2);

        if ($remaining <= 0) {
            return;
        }

        $advances = CashAdvance::query()
            ->where('employee_id', $payroll->employee_id)
            ->outstanding()
            ->orderBy('paid_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        abort_if(
            round($advances->sum(fn (CashAdvance $advance) => $advance->balance()), 2) < $remaining,
            422,
            'Outstanding cash advance balance changed; edit the payroll deduction first.'
        );

        foreach ($advances as $advance) {
            $take = round(min($remaining, $advance->balance()), 2);
            $advance->repaid_amount = round((float) $advance->repaid_amount + $take, 2);
            $advance->save();

            CashAdvanceRepayment::create([
                'cash_advance_id' => $advance->id,
                'payroll_id' => $payroll->id,
                'amount' => $take,
            ]);

            $remaining = round($remaining - $take, 2);

            if ($remaining <= 0) {
                break;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCashAdvance(CashAdvance $advance): array
    {
        return [
            'id' => $advance->id,
            'amount' => (float) $advance->amount,
            'repaid_amount' => (float) $advance->repaid_amount,
            'balance' => $advance->balance(),
            'method' => $advance->method,
            'method_label' => SaleTransaction::paymentMethodLabel($advance->method),
            'reference_number' => $advance->reference_number,
            'notes' => $advance->notes,
            'paid_at' => $advance->paid_at?->toISOString(),
            'released_by_name' => $advance->releasedBy?->name,
            'voided' => $advance->isVoided(),
            'voidable' => ! $advance->isVoided() && (float) $advance->repaid_amount <= 0,
            'void_reason' => $advance->void_reason,
            'voided_by_name' => $advance->voidedBy?->name,
            'voided_at' => $advance->voided_at?->toISOString(),
        ];
    }

    /**
     * Allow employees to view their own records; everything else needs the permission.
     */
    private function authorizeSelfOrManager(User $employee): void
    {
        abort_unless(
            (int) $employee->id === (int) auth()->id() || auth()->user()->can('manage employees'),
            403
        );
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
        ];
    }

    /**
     * @param  array{
     *     daily_rate?: float|int|string|null,
     *     pay_frequency?: string|null,
     *     pt_commission_rate?: float|int|string|null,
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
                'pt_commission_rate' => $attributes['pt_commission_rate'] ?? $profile->pt_commission_rate,
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
                'pt_commission_rate',
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
            'pt_commission_rate' => round((float) ($employeeProfile->pt_commission_rate ?? 0), 2),
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
     * @return list<array{id: int, day_of_week: int, start_time: string, end_time: string}>
     */
    private function serializeScheduleShifts(EmployeeProfile $profile): array
    {
        return $profile->scheduleShifts
            ->map(fn (EmployeeScheduleShift $shift) => [
                'id' => $shift->id,
                'day_of_week' => (int) $shift->day_of_week,
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{day_of_week: int|string, start_time: string, end_time: string}>  $shifts
     */
    private function assertNoOverlap(array $shifts): void
    {
        $byDay = [];
        foreach ($shifts as $index => $shift) {
            $byDay[(int) $shift['day_of_week']][] = ['index' => $index, 'shift' => $shift];
        }

        $errors = [];
        foreach ($byDay as $dayShifts) {
            usort($dayShifts, fn ($a, $b) => strcmp($a['shift']['start_time'], $b['shift']['start_time']));

            for ($i = 1; $i < count($dayShifts); $i++) {
                $prev = $dayShifts[$i - 1]['shift'];
                $curr = $dayShifts[$i];
                if ($curr['shift']['start_time'] < $prev['end_time']) {
                    $errors["shifts.{$curr['index']}.start_time"] = ['Shift overlaps another shift on the same day.'];
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @return array{payroll_withholding_tax_enabled: bool, payroll_government_contributions_enabled: bool}
     */
    private function serializeBusinessProfilePayrollSettings(BusinessProfile $businessProfile): array
    {
        return [
            'payroll_withholding_tax_enabled' => (bool) $businessProfile->payroll_withholding_tax_enabled,
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
            'pt_commission_rate' => round((float) ($attributes['pt_commission_rate'] ?? 0), 2),
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

    /**
     * Normalize an optional monetary value.
     *
     * @return ?float
     */
    private function normalizeNullableMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * Determine whether payroll uses Philippine settings.
     *
     * @return bool
     */
    private function isPhilippinesPayrollBusiness(?BusinessProfile $businessProfile = null): bool
    {
        return ($businessProfile ?? BusinessProfile::current())->country_code === BusinessProfile::COUNTRY_PHILIPPINES;
    }

    /**
     * Build the default Hikvision employee number.
     *
     * @return string
     */
    private function defaultHikvisionEmployeeNo(int $employeeId): string
    {
        return str_pad((string) $employeeId, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Return the employee daily rate.
     *
     * @return float
     */
    private function employeeDailyRate(User $employee): float
    {
        $employee->loadMissing('employeeProfile');

        return round((float) ($employee->employeeProfile?->daily_rate ?? 0), 2);
    }

    /**
     * Return the employee pay frequency.
     *
     * @return ?string
     */
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
        $payroll->loadMissing('approvedBy:id,name');

        // Prefer the SQL aggregates when the caller used withSum/withCount (list endpoint);
        // otherwise fall back to loading the payouts of this single payroll.
        $hasAggregates = array_key_exists('payouts_sum_amount', $payroll->getAttributes());

        if (! $hasAggregates) {
            $payroll->loadMissing('payouts');
        }

        $totalPaid = $hasAggregates
            ? (float) ($payroll->payouts_sum_amount ?? 0)
            : (float) $payroll->payouts->sum('amount');
        $payoutsCount = $hasAggregates
            ? (int) $payroll->payouts_count
            : $payroll->payouts->count();
        $manualGrossAdjustmentAmount = $payroll->manualGrossAdjustmentAmount();

        return [
            'id' => $payroll->id,
            'period_start' => $payroll->period_start->format('Y-m-d'),
            'period_end' => $payroll->period_end->format('Y-m-d'),
            'regular_hours' => $payroll->regular_hours !== null ? (float) $payroll->regular_hours : null,
            'regular_pay_amount' => $payroll->regular_pay_amount !== null ? (float) $payroll->regular_pay_amount : null,
            'overwork_hours' => $payroll->overwork_hours !== null ? (float) $payroll->overwork_hours : null,
            'overwork_pay_amount' => $payroll->overwork_pay_amount !== null ? (float) $payroll->overwork_pay_amount : null,
            'commission_amount' => $payroll->commission_amount !== null ? (float) $payroll->commission_amount : null,
            'manual_gross_adjustment_amount' => $manualGrossAdjustmentAmount,
            'gross_amount' => (float) $payroll->gross_amount,
            'pay_frequency' => $payroll->pay_frequency,
            'employee_contributions' => $payroll->employee_contributions ?? [],
            'employee_contributions_total' => $payroll->employeeContributionsTotal(),
            'employee_deductions_total' => $payroll->employeeDeductionsTotal(),
            'employer_contributions' => $payroll->employer_contributions ?? [],
            'employer_contributions_total' => $payroll->employerContributionsTotal(),
            'total_earnings' => $payroll->totalEarnings(),
            'withholding_tax' => (float) $payroll->withholding_tax,
            'manual_deductions' => (float) $payroll->manual_deductions,
            'cash_advance_deductions' => (float) $payroll->cash_advance_deductions,
            'net_amount' => (float) $payroll->net_amount,
            'status' => $payroll->status,
            'notes' => $payroll->notes,
            'total_paid' => $totalPaid,
            'remaining_balance' => max(0, (float) $payroll->net_amount - $totalPaid),
            'payouts_count' => $payoutsCount,
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
    private function employeeSystemActivitySnapshot(User $employee): array
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
    private function payrollSystemActivitySnapshot(Payroll $payroll, User $employee): array
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
            'withholding_tax' => round((float) $payroll->withholding_tax, 2),
            'employee_contributions' => $payroll->employee_contributions ?? [],
            'employee_contributions_total' => $payroll->employeeContributionsTotal(),
            'employer_contributions' => $payroll->employer_contributions ?? [],
            'employer_contributions_total' => $payroll->employerContributionsTotal(),
            'cash_advance_deductions' => round((float) $payroll->cash_advance_deductions, 2),
            'net_amount' => round((float) $payroll->net_amount, 2),
            'status' => $payroll->status,
        ];
    }

}
