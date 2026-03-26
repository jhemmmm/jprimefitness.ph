<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\Payroll;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function __construct(private PayrollService $payrollService) {}

    public function listForEmployee(User $employee): JsonResponse
    {
        $payouts = Payout::where('employee_id', $employee->id)
            ->with(['payroll:id,period_start,period_end', 'releasedBy:id,first_name,last_name'])
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (Payout $p) => $this->formatPayout($p));

        return response()->json($payouts);
    }

    public function listForPayroll(Payroll $payroll): JsonResponse
    {
        $payouts = $payroll->payouts()
            ->with('releasedBy:id,first_name,last_name')
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn ($p) => $this->formatPayout($p));

        return response()->json($payouts);
    }

    public function store(Request $request, Payroll $payroll): JsonResponse
    {
        if (! in_array($payroll->status, ['approved', 'partially_paid'])) {
            return response()->json(['message' => 'Payroll must be approved before adding a payout.'], 422);
        }

        $remaining = $payroll->remainingBalance();

        $validated = $request->validate([
            'amount' => "required|numeric|min:0.01|max:{$remaining}",
            'method' => 'required|in:cash,gcash,bank',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'paid_at' => 'nullable|date',
        ]);

        $payout = $payroll->payouts()->create([
            'employee_id' => $payroll->employee_id,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'reference_number' => $validated['reference_number'] ?? null,
            'released_by' => auth()->id(),
            'notes' => $validated['notes'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now(),
        ]);

        $this->payrollService->syncStatus($payroll);

        return response()->json($this->formatPayout($payout->load('releasedBy:id,first_name,last_name')), 201);
    }

    public function destroy(Payout $payout): JsonResponse
    {
        $payroll = $payout->payroll;
        $payout->delete();
        $this->payrollService->syncStatus($payroll);

        return response()->json(null, 204);
    }

    private function formatPayout(Payout $p): array
    {
        return [
            'id' => $p->id,
            'payroll_id' => $p->payroll_id,
            'amount' => (float) $p->amount,
            'method' => $p->method,
            'reference_number' => $p->reference_number,
            'notes' => $p->notes,
            'paid_at' => $p->paid_at?->toISOString(),
            'released_by_name' => $p->releasedBy?->full_name,
            'payroll_period' => isset($p->payroll)
                ? $p->payroll->period_start.' – '.$p->payroll->period_end
                : null,
        ];
    }
}
