<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CashAdvance;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use App\Services\BranchCashLedgerService;
use App\Services\PayrollService;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class EmployeeController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private PayrollService $payrollService,
        private BranchCashLedgerService $branchCashLedgerService,
    ) {
        $this->middleware('can:manage employees');
    }

    /**
     * Index
     *
     * @return View
     */
    public function index()
    {
        return view('panel.employees.index');
    }

    /**
     * Show the specified employee
     *
     * @return View
     */
    public function show(User $employee)
    {
        return view('panel.employees.show', [
            'employee' => $employee->load('branches', 'roles'),
        ]);
    }

    /**
     * List with filters and pagination
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $employees = User::role(['employee', 'coach', 'manager', 'admin', 'staff'])
            ->with('branches', 'roles')
            ->when(!empty($request->search), fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%");
            }))
            ->when(!empty($request->role), fn($q) => $q->whereHas('roles', fn($qq) => $qq->where('id', $request->role)))
            ->when(!auth()->user()->hasRole('super admin'), fn($q) => $q->whereHas('branches', fn($qq) => $qq->whereIn('branches.id', auth()->user()->branches()->pluck('branches.id'))))
            ->when($request->branch, fn($q) => $q->whereHas('branches', fn($qq) => $qq->where('branches.id', $request->branch)))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->get();

        return response()->json($employees);
    }

    /**
     * Store newly created employee
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => ['integer', Rule::in(auth()->user()->allowedEmployeesRoles())],
            'daily_rate' => 'required|numeric|min:0',
            'pay_frequency' => ['required', Rule::in([Branch::PAYROLL_FREQUENCY_MONTHLY, Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY])],
            'password' => 'required|string|min:8',
        ]);

        $employee = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'daily_rate' => $data['daily_rate'],
            'pay_frequency' => $data['pay_frequency'],
            'password' => Hash::make($data['password']),
        ]);

        $employee->branches()->attach($data['branch_ids']);
        $employee->roles()->attach($data['role_ids']);

        return response()->json($employee->load('branches', 'roles'), 201);
    }

    /**
     * Update the specified employee
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function update(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,' . $employee->id,
            'phone' => 'nullable|string|max:20',
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED])],
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => ['integer', Rule::in(auth()->user()->allowedEmployeesRoles())],
            'daily_rate' => 'required|numeric|min:0',
            'pay_frequency' => ['required', Rule::in([Branch::PAYROLL_FREQUENCY_MONTHLY, Branch::PAYROLL_FREQUENCY_SEMI_MONTHLY])],
            'password' => 'nullable|string|min:8',
        ]);

        $employee->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'daily_rate' => $data['daily_rate'],
            'pay_frequency' => $data['pay_frequency'],
            'password' => isset($data['password']) ? Hash::make($data['password']) : $employee->password,
        ]);

        $employee->branches()->sync($data['branch_ids']);

        $employee->roles()->sync($data['role_ids']);

        return response()->json($employee->fresh()->load('branches', 'roles'));
    }

    /**
     * Delete the specified employee
     * @param User $employee
     * @return JsonResponse
     */
    public function destroy(User $employee): JsonResponse
    {
        abort_if($employee->id === auth()->id(), 403);

        $employee->delete();

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * Get the attendance of the employee
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function attendance(Request $request, User $employee): JsonResponse
    {
        $record = Attendance::where('user_id', $employee->id)
            ->with('branch')
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('checked_in_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('checked_in_at', '<=', $request->date_to))
            ->orderByDesc('checked_in_at')
            ->paginate(15);

        $statsQuery = Attendance::where('user_id', $employee->id);

        return response()->json([
            'records' => $record,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'this_month' => (clone $statsQuery)->whereMonth('checked_in_at', now()->month)->whereYear('checked_in_at', now()->year)->count(),
                'currently_in' => (clone $statsQuery)->whereNull('checked_out_at')->count(),
            ],
        ]);
    }

    /**
     * Get payrolls of the employee
     * @param User $employee
     * @return JsonResponse
     */
    public function payrolls(User $employee): JsonResponse
    {
        $payrolls = Payroll::query()
            ->where('employee_id', $employee->id)
            ->with(['payouts', 'approvedBy:id,name', 'branch:id,country_code'])
            ->orderByDesc('period_start')
            ->get()
            ->map(fn(Payroll $payroll) => $this->serializePayroll($payroll));

        return response()->json($payrolls);
    }

    /**
     * Get the payslip of the employee
     * @param User $employee
     * @param Payroll $payroll
     * @return PdfBuilder
     */
    public function payslip(User $employee, Payroll $payroll): Responsable
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        $payroll->load([
            'branch',
            'employee.branches',
            'employee.roles',
            'generatedBy:id,name',
            'approvedBy:id,name',
            'payouts' => fn($query) => $query
                ->with('releasedBy:id,name')
                ->orderBy('paid_at'),
        ]);

        $employee = $employee->fresh()->load('branches', 'roles');
        $fileName = 'payslip-employee-' . $employee->id . '-payroll-' . $payroll->id . '.pdf';

        return Pdf::view('panel.employees.payslip', [
            'employee' => $employee,
            'payroll' => $payroll,
        ])->driver('dompdf')->format('a4')->margins(8, 8, 8, 8)->download($fileName);
    }

    /**
     * Store a payroll draft and snapshot the calculated totals for the selected period.
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function storePayroll(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'gross_amount' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'manual_deductions' => 'nullable|numeric|min:0',
            'cash_advance_deduction' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if employee has an assigned branch
        $branch = $employee->branches()->orderBy('branches.id')->first();
        if (!$branch) {
            return response()->json(['message' => 'Employee must be assigned to a branch before creating payroll.'], 422);
        }

        $gross = (float) $data['gross_amount'];
        $bonus = (float) ($data['bonus'] ?? 0);
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceDeduction = (float) ($data['cash_advance_deduction'] ?? 0);
        $payFrequency = $employee->pay_frequency;
        $payrollTaxContext = [
            'employee_id' => $employee->id,
            'period_end' => $data['period_end'],
        ];
        $ptCommissionSummary = $this->payrollService->previewPtCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            null,
            $branch->id
        );
        $membershipCommissionSummary = $this->payrollService->previewMembershipCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            null,
            $branch->id
        );
        $maxCashAdvanceDeduction = $this->payrollService->maxCashAdvanceDeduction(
            $employee->id,
            $branch->country_code,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );
        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $branch->country_code,
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
            'branch_id' => $branch->id,
            'pay_frequency' => $payFrequency,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
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

        return response()->json($this->serializePayroll($payroll), 201);
    }

    /**
     * Recalculate and persist a draft payroll after payroll inputs change.
     * @param Request $request
     * @param User $employee
     * @param Payroll $payroll
     * @return JsonResponse
     */
    public function updatePayroll(Request $request, User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if ($payroll->status !== Payroll::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft payrolls can be edited.'], 422);
        }

        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'gross_amount' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'manual_deductions' => 'nullable|numeric|min:0',
            'cash_advance_deduction' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $payroll->loadMissing('branch');
        $gross = (float) $data['gross_amount'];
        $bonus = (float) ($data['bonus'] ?? 0);
        $manualDeductions = (float) ($data['manual_deductions'] ?? 0);
        $cashAdvanceDeduction = (float) ($data['cash_advance_deduction'] ?? 0);
        $payFrequency = $employee->pay_frequency;
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
            $payroll->branch_id
        );
        $membershipCommissionSummary = $this->payrollService->previewMembershipCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
            $payroll->branch_id
        );
        $maxCashAdvanceDeduction = $this->payrollService->maxCashAdvanceDeduction(
            $employee->id,
            $payroll->branch?->country_code,
            $payFrequency,
            $gross,
            $bonus,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );
        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $payroll->branch?->country_code,
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

        return response()->json($this->serializePayroll($payroll));
    }

    /**
     * Approved the specified payroll of the employee
     * @param User $employee
     * @param Payroll $payroll
     * @return JsonResponse
     */
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

        return response()->json($this->serializePayroll($payroll));
    }

    /**
     * Cancel the specified payroll of the employee
     * @param User $employee
     * @param Payroll $payroll
     * @return JsonResponse
     */
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

        return response()->json($this->serializePayroll($payroll));
    }

    /**
     * Get suggested cash advance deduction for the specified employee
     * @param User $employee
     * @return JsonResponse
     */
    public function payrollSuggestedCa(User $employee): JsonResponse
    {
        return response()->json([
            'suggested_ca' => $this->payrollService->pendingCaTotal($employee->id),
        ]);
    }

    /**
     * Preview payroll totals without saving so the modal stays in sync with the backend rules.
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function payrollSuggest(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'payroll_id' => 'nullable|integer|exists:payrolls,id',
            'gross_amount' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'manual_deductions' => 'nullable|numeric|min:0',
            'cash_advance_deduction' => 'nullable|numeric|min:0',
        ]);

        $payroll = null;

        if (!empty($data['payroll_id'])) {
            $payroll = Payroll::find($data['payroll_id']);

            if ($payroll && $payroll->employee_id !== $employee->id) {
                abort(404);
            }

            $payroll?->loadMissing('branch');
        }

        $branchId = $payroll?->branch_id ?? $employee->branches()->orderBy('branches.id')->value('branches.id');

        $ptCommissionSummary = $this->payrollService->previewPtCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
            $branchId
        );
        $membershipCommissionSummary = $this->payrollService->previewMembershipCommissions(
            $employee,
            $data['period_start'],
            $data['period_end'],
            $payroll,
            $branchId
        );
        $attendanceSuggestion = $this->payrollService->suggestFromAttendance(
            $employee,
            $data['period_start'],
            $data['period_end']
        );
        $branchCountryCode = $payroll?->branch?->country_code
            ?? $employee->branches()->whereKey($branchId)->value('country_code');
        $payFrequency = $payroll?->pay_frequency ?? $employee->pay_frequency;
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
            $branchCountryCode,
            $payFrequency,
            $grossAmount,
            $bonusAmount,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );
        $payrollTotals = $this->payrollService->calculatePayrollTotals(
            $branchCountryCode,
            $payFrequency,
            $grossAmount,
            $bonusAmount,
            $ptCommissionSummary['amount'],
            $manualDeductions,
            $cashAdvanceDeduction,
            $membershipCommissionSummary['amount'],
            $payrollTaxContext
        );

        return response()->json(
            array_merge(
                $attendanceSuggestion,
                [
                    'pt_commission_amount' => $ptCommissionSummary['amount'],
                    'pt_commission_items' => $ptCommissionSummary['items'],
                    'membership_commission_amount' => $membershipCommissionSummary['amount'],
                    'membership_commission_items' => $membershipCommissionSummary['items'],
                    'branch_country_code' => $branchCountryCode,
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
                ]
            )
        );
    }

    /**
     * Get all payouts for the specified employee
     * @param User $employee
     * @return JsonResponse
     */
    public function payouts(User $employee): JsonResponse
    {
        $payouts = Payout::query()
            ->where('employee_id', $employee->id)
            ->with(['payroll:id,period_start,period_end', 'releasedBy:id,name'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(function (Payout $payout) {
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
                        ? $payout->payroll->period_start->format('Y-m-d') . ' – ' . $payout->payroll->period_end->format('Y-m-d')
                        : null,
                ];
            });

        return response()->json($payouts);
    }

    /**
     * Get all payouts for the specified payroll
     * @param User $employee
     * @param Payroll $payroll
     * @return JsonResponse
     */
    public function payrollPayouts(User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        $payouts = $payroll->payouts()
            ->with('releasedBy:id,name')
            ->orderByDesc('paid_at')
            ->get()
            ->map(function (Payout $payout) {
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
                        ? $payout->payroll->period_start->format('Y-m-d') . ' – ' . $payout->payroll->period_end->format('Y-m-d')
                        : null,
                ];
            });

        return response()->json($payouts);
    }

    /**
     * Store a new payout for the specified payroll
     * @param Request $request
     * @param User $employee
     * @param Payroll $payroll
     * @return JsonResponse
     */
    public function storePayout(Request $request, User $employee, Payroll $payroll): JsonResponse
    {
        abort_if($payroll->employee_id !== $employee->id, 404);

        if (!in_array($payroll->status, [Payroll::STATUS_APPROVED, Payroll::STATUS_PARTIALLY_PAID], true)) {
            return response()->json(['message' => 'Payroll must be approved before adding a payout.'], 422);
        }

        $remaining = $payroll->remainingBalance();

        if ($remaining <= 0) {
            return response()->json(['message' => 'Payroll has no remaining balance for payout.'], 422);
        }

        $data = $request->validate([
            'amount' => "required|numeric|min:0.01|max:{$remaining}",
            'method' => ['required', Rule::in([Payout::METHOD_CASH, Payout::METHOD_BANK_TRANSFER, Payout::METHOD_ONLINE_PAYMENT])],
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'paid_at' => 'nullable|date',
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
        $this->branchCashLedgerService->syncPayout($payout);

        $payout->load('releasedBy:id,name');

        return response()->json([
            'id' => $payout->id,
            'payroll_id' => $payout->payroll_id,
            'amount' => (float) $payout->amount,
            'method' => $payout->method,
            'reference_number' => $payout->reference_number,
            'notes' => $payout->notes,
            'paid_at' => $payout->paid_at?->toISOString(),
            'released_by_name' => $payout->releasedBy?->name,
            'payroll_period' => null,
        ], 201);
    }

    /**
     * Get all cash advances for the specified employee
     * @param User $employee
     * @return JsonResponse
     */
    public function cashAdvances(User $employee): JsonResponse
    {
        $advances = CashAdvance::query()
            ->where('employee_id', $employee->id)
            ->with(['approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(function (CashAdvance $cashAdvance) {
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
                    'audit_data' => $cashAdvance->audit_data ?? [],
                    'created_at' => $cashAdvance->created_at->toISOString(),
                ];
            });

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
                'released_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_RELEASED)->count(),
                'partially_paid_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_PARTIALLY_PAID)->count(),
                'paid_count' => (clone $statsQuery)->where('status', CashAdvance::STATUS_PAID)->count(),
            ],
        ]);
    }

    /**
     * Store a new cash advance request for the specified employee
     * @param Request $request
     * @param User $employee
     * @return JsonResponse
     */
    public function storeCashAdvance(Request $request, User $employee): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
            'requested_at' => 'nullable|date',
        ]);

        $cashAdvance = CashAdvance::create([
            'employee_id' => $employee->id,
            'branch_id' => $employee->branches()->first()?->id,
            'amount' => $data['amount'],
            'remaining_amount' => $data['amount'],
            'status' => CashAdvance::STATUS_REQUESTED,
            'notes' => $data['notes'] ?? null,
            'requested_at' => $data['requested_at'] ?? now(),
        ]);
        $cashAdvance->appendAuditEvent([
            'event' => CashAdvance::STATUS_REQUESTED,
            'at' => $cashAdvance->requested_at?->toISOString(),
            'by_user_id' => $employee->id,
            'by_name' => $employee->name,
            'source' => 'panel',
            'notes' => $cashAdvance->notes,
        ]);
        $cashAdvance->save();

        return response()->json([
            'id' => $cashAdvance->id,
            'amount' => (float) $cashAdvance->amount,
            'remaining_amount' => (float) $cashAdvance->remaining_amount,
            'deducted_amount' => round((float) $cashAdvance->amount - (float) $cashAdvance->remaining_amount, 2),
            'status' => $cashAdvance->status,
            'notes' => $cashAdvance->notes,
            'requested_at' => $cashAdvance->requested_at?->toISOString(),
            'approved_at' => null,
            'approved_by_name' => null,
            'released_at' => null,
            'released_by_name' => null,
            'cancelled_at' => null,
            'cancelled_by_name' => null,
            'paid_at' => null,
            'audit_data' => $cashAdvance->audit_data ?? [],
            'created_at' => $cashAdvance->created_at->toISOString(),
        ], 201);
    }

    /**
     * Update the specified cash advance request of the employee
     * @param Request $request
     * @param User $employee
     * @param CashAdvance $cashAdvance
     * @return JsonResponse
     */
    public function updateCashAdvance(Request $request, User $employee, CashAdvance $cashAdvance): JsonResponse
    {
        abort_if($cashAdvance->employee_id !== $employee->id, 404);

        if (
            in_array($cashAdvance->status, [
                CashAdvance::STATUS_RELEASED,
                CashAdvance::STATUS_PARTIALLY_PAID,
                CashAdvance::STATUS_PAID,
                CashAdvance::STATUS_CANCELLED,
            ], true)
        ) {
            return response()->json(['message' => 'Released and finalized cash advances cannot be edited here.'], 422);
        }

        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:1',
            'status' => [
                'sometimes',
                Rule::in([
                    CashAdvance::STATUS_REQUESTED,
                    CashAdvance::STATUS_APPROVED,
                    CashAdvance::STATUS_RELEASED,
                    CashAdvance::STATUS_CANCELLED,
                ]),
            ],
            'notes' => 'nullable|string|max:500',
            'requested_at' => 'nullable|date',
        ]);

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

        if (!in_array($nextStatus, $allowedTransitions, true)) {
            return response()->json(['message' => 'Invalid cash advance status transition.'], 422);
        }

        $previousStatus = $cashAdvance->status;
        $actor = auth()->user();

        $cashAdvance->update($data);

        if ($previousStatus !== $cashAdvance->status) {
            if ($cashAdvance->status === CashAdvance::STATUS_APPROVED && !$cashAdvance->approved_at) {
                $cashAdvance->approved_at = now();
                $cashAdvance->approved_by = auth()->id();
            }

            if ($cashAdvance->status === CashAdvance::STATUS_RELEASED && !$cashAdvance->released_at) {
                $cashAdvance->released_at = now();
                $cashAdvance->released_by = auth()->id();
            }

            if ($cashAdvance->status === CashAdvance::STATUS_CANCELLED && !$cashAdvance->cancelled_at) {
                $cashAdvance->cancelled_at = now();
                $cashAdvance->cancelled_by = auth()->id();
            }

            $eventAt = match ($cashAdvance->status) {
                CashAdvance::STATUS_APPROVED => $cashAdvance->approved_at,
                CashAdvance::STATUS_RELEASED => $cashAdvance->released_at,
                CashAdvance::STATUS_CANCELLED => $cashAdvance->cancelled_at,
                default => now(),
            };

            $cashAdvance->appendAuditEvent([
                'event' => $cashAdvance->status,
                'at' => $eventAt?->toISOString(),
                'by_user_id' => $actor?->id,
                'by_name' => $actor?->name,
                'source' => 'panel',
                'notes' => $cashAdvance->notes,
            ]);
        }

        $cashAdvance->remaining_amount = (float) $cashAdvance->amount;
        $cashAdvance->save();
        $cashAdvance->load(['approvedBy:id,name', 'releasedBy:id,name', 'cancelledBy:id,name']);
        $this->branchCashLedgerService->syncCashAdvance($cashAdvance);

        return response()->json([
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
            'audit_data' => $cashAdvance->audit_data ?? [],
            'created_at' => $cashAdvance->created_at->toISOString(),
        ]);
    }

    /**
     * Delete the specified cash advance request of the employee
     * @param User $employee
     * @param CashAdvance $cashAdvance
     * @return JsonResponse
     */
    public function destroyCashAdvance(User $employee, CashAdvance $cashAdvance): JsonResponse
    {
        abort_if($cashAdvance->employee_id !== $employee->id, 404);

        if (
            in_array($cashAdvance->status, [
                CashAdvance::STATUS_RELEASED,
                CashAdvance::STATUS_PARTIALLY_PAID,
                CashAdvance::STATUS_PAID,
                CashAdvance::STATUS_CANCELLED,
            ], true)
        ) {
            return response()->json(['message' => 'Finalized cash advances cannot be deleted.'], 422);
        }

        $this->branchCashLedgerService->syncCashAdvance($cashAdvance);
        $cashAdvance->delete();

        return response()->json(null, 204);
    }

    /**
     * Get the serialized payroll data
     * @param Payroll $payroll
     * @return array{approved_at: string|null, approved_by_name: string|null, bonus: float, branch_country_code: string|null, cash_advance_deduction: float, created_at: string|null, employee_deductions_total: float, gross_amount: float, id: int, income_tax: float, manual_deductions: float, membership_commission_amount: float, membership_commission_items: array, net_amount: float, notes: string|null, pay_frequency: string|null, payouts_count: int, period_end: string, period_start: string, pt_commission_amount: float, pt_commission_items: array, remaining_balance: float|int, status: string, total_earnings: float, total_paid: float}
     */
    private function serializePayroll(Payroll $payroll): array
    {
        $payroll->loadMissing(['payouts', 'approvedBy:id,name', 'branch:id,country_code']);

        $totalPaid = (float) $payroll->payouts->sum('amount');

        return [
            'id' => $payroll->id,
            'period_start' => $payroll->period_start->format('Y-m-d'),
            'period_end' => $payroll->period_end->format('Y-m-d'),
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
            'branch_country_code' => $payroll->branch?->country_code,
            'created_at' => $payroll->created_at->toISOString(),
        ];
    }
}
