<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashAdvanceController extends Controller
{
    public function list(User $employee): JsonResponse
    {
        $advances = CashAdvance::where('employee_id', $employee->id)
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn ($a) => $this->formatAdvance($a));

        $stats = [
            'total_amount'     => (float) CashAdvance::where('employee_id', $employee->id)->sum('amount'),
            'total_remaining'  => (float) CashAdvance::where('employee_id', $employee->id)->sum('remaining_amount'),
            'pending_count'    => CashAdvance::where('employee_id', $employee->id)->where('status', 'pending')->count(),
            'partial_count'    => CashAdvance::where('employee_id', $employee->id)->where('status', 'partial')->count(),
            'deducted_count'   => CashAdvance::where('employee_id', $employee->id)->where('status', 'fully_deducted')->count(),
        ];

        return response()->json(compact('advances', 'stats'));
    }

    public function store(Request $request, User $employee): JsonResponse
    {
        $validated = $request->validate([
            'amount'       => 'required|numeric|min:1',
            'notes'        => 'nullable|string|max:500',
            'requested_at' => 'nullable|date',
        ]);

        $primaryBranchId = $employee->branches()->first()?->id;

        $advance = CashAdvance::create([
            'employee_id'      => $employee->id,
            'branch_id'        => $primaryBranchId,
            'amount'           => $validated['amount'],
            'remaining_amount' => $validated['amount'],
            'notes'            => $validated['notes'] ?? null,
            'requested_at'     => $validated['requested_at'] ?? now(),
        ]);

        return response()->json($this->formatAdvance($advance), 201);
    }

    public function update(Request $request, CashAdvance $cashAdvance): JsonResponse
    {
        if ($cashAdvance->status === 'fully_deducted') {
            return response()->json(['message' => 'Fully deducted advances cannot be edited.'], 422);
        }

        $validated = $request->validate([
            'amount'           => 'sometimes|numeric|min:1',
            'remaining_amount' => 'sometimes|numeric|min:0',
            'notes'            => 'nullable|string|max:500',
        ]);

        $cashAdvance->update($validated);

        // Recalculate status
        if ((float) $cashAdvance->remaining_amount <= 0) {
            $cashAdvance->status = 'fully_deducted';
        } elseif ((float) $cashAdvance->remaining_amount < (float) $cashAdvance->amount) {
            $cashAdvance->status = 'partial';
        } else {
            $cashAdvance->status = 'pending';
        }
        $cashAdvance->save();

        return response()->json($this->formatAdvance($cashAdvance));
    }

    public function destroy(CashAdvance $cashAdvance): JsonResponse
    {
        if ($cashAdvance->status === 'fully_deducted') {
            return response()->json(['message' => 'Fully deducted advances cannot be deleted.'], 422);
        }

        $cashAdvance->delete();

        return response()->json(null, 204);
    }

    private function formatAdvance(CashAdvance $a): array
    {
        return [
            'id'               => $a->id,
            'amount'           => (float) $a->amount,
            'remaining_amount' => (float) $a->remaining_amount,
            'deducted_amount'  => round((float) $a->amount - (float) $a->remaining_amount, 2),
            'status'           => $a->status,
            'notes'            => $a->notes,
            'requested_at'     => $a->requested_at?->toISOString(),
            'created_at'       => $a->created_at->toISOString(),
        ];
    }
}
