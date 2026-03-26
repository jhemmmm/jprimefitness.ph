<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payrollService) {}

    public function list(User $employee): JsonResponse
    {
        $payrolls = Payroll::where('employee_id', $employee->id)
            ->with(['payouts', 'approvedBy:id,first_name,last_name'])
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (Payroll $p) => $this->formatPayroll($p));

        return response()->json($payrolls);
    }

    public function store(Request $request, User $employee): JsonResponse
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'gross_amount' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'manual_deductions' => 'nullable|numeric|min:0',
            'cash_advance_deduction' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $gross = (float) $validated['gross_amount'];
        $bonus = (float) ($validated['bonus'] ?? 0);
        $manDed = (float) ($validated['manual_deductions'] ?? 0);
        $caDed = (float) ($validated['cash_advance_deduction'] ?? 0);

        $primaryBranchId = $employee->branches()->first()?->id;

        $payroll = Payroll::create([
            'employee_id' => $employee->id,
            'branch_id' => $primaryBranchId,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'gross_amount' => $gross,
            'bonus' => $bonus,
            'manual_deductions' => $manDed,
            'cash_advance_deduction' => $caDed,
            'net_amount' => $this->payrollService->computeNet($gross, $bonus, $manDed, $caDed),
            'notes' => $validated['notes'] ?? null,
            'generated_by' => auth()->id(),
        ]);

        return response()->json($this->formatPayroll($payroll->load('payouts')), 201);
    }

    public function update(Request $request, Payroll $payroll): JsonResponse
    {
        if ($payroll->status !== 'draft') {
            return response()->json(['message' => 'Only draft payrolls can be edited.'], 422);
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'gross_amount' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'manual_deductions' => 'nullable|numeric|min:0',
            'cash_advance_deduction' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $gross = (float) $validated['gross_amount'];
        $bonus = (float) ($validated['bonus'] ?? 0);
        $manDed = (float) ($validated['manual_deductions'] ?? 0);
        $caDed = (float) ($validated['cash_advance_deduction'] ?? 0);

        $payroll->update([
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'gross_amount' => $gross,
            'bonus' => $bonus,
            'manual_deductions' => $manDed,
            'cash_advance_deduction' => $caDed,
            'net_amount' => $this->payrollService->computeNet($gross, $bonus, $manDed, $caDed),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($this->formatPayroll($payroll->load('payouts')));
    }

    public function approve(Payroll $payroll): JsonResponse
    {
        if ($payroll->status !== 'draft') {
            return response()->json(['message' => 'Only draft payrolls can be approved.'], 422);
        }

        $payroll->status = 'approved';
        $payroll->approved_by = auth()->id();
        $payroll->approved_at = now();
        $payroll->save();

        $this->payrollService->applyAdvances($payroll);

        return response()->json($this->formatPayroll($payroll->load(['payouts', 'approvedBy:id,first_name,last_name'])));
    }

    public function destroy(Payroll $payroll): JsonResponse
    {
        if ($payroll->status === 'paid') {
            return response()->json(['message' => 'Paid payrolls cannot be deleted.'], 422);
        }

        $payroll->delete();

        return response()->json(null, 204);
    }

    public function suggestedCa(User $employee): JsonResponse
    {
        return response()->json([
            'suggested_ca' => $this->payrollService->pendingCaTotal($employee->id),
        ]);
    }

    public function suggest(Request $request, User $employee): JsonResponse
    {
        $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        return response()->json(
            $this->payrollService->suggestFromAttendance(
                $employee,
                $request->period_start,
                $request->period_end
            )
        );
    }

    private function formatPayroll(Payroll $p): array
    {
        $totalPaid = (float) $p->payouts->sum('amount');

        return [
            'id' => $p->id,
            'period_start' => $p->period_start->format('Y-m-d'),
            'period_end' => $p->period_end->format('Y-m-d'),
            'gross_amount' => (float) $p->gross_amount,
            'bonus' => (float) $p->bonus,
            'manual_deductions' => (float) $p->manual_deductions,
            'cash_advance_deduction' => (float) $p->cash_advance_deduction,
            'net_amount' => (float) $p->net_amount,
            'status' => $p->status,
            'notes' => $p->notes,
            'total_paid' => $totalPaid,
            'remaining_balance' => max(0, (float) $p->net_amount - $totalPaid),
            'payouts_count' => $p->payouts->count(),
            'approved_by_name' => $p->approvedBy ? $p->approvedBy->full_name : null,
            'approved_at' => $p->approved_at?->toISOString(),
            'created_at' => $p->created_at->toISOString(),
        ];
    }
}
