<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchCashLedgerEntry;
use App\Models\CashAdvance;
use App\Models\Payout;
use App\Models\SaleTransaction;
use App\Models\WalkIn;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BranchCashLedgerService
{
    /**
     * @return array<string, float|int|string|null>
     */
    public function summarize(Branch $branch): array
    {
        $baseQuery = $branch->cashLedgerEntries();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $cashInTotal = round((clone $baseQuery)
            ->where('direction', BranchCashLedgerEntry::DIRECTION_IN)
            ->sum('amount'), 2);

        $cashOutTotal = round((clone $baseQuery)
            ->where('direction', BranchCashLedgerEntry::DIRECTION_OUT)
            ->sum('amount'), 2);

        $monthCashIn = round((clone $baseQuery)
            ->where('direction', BranchCashLedgerEntry::DIRECTION_IN)
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->sum('amount'), 2);

        $monthCashOut = round((clone $baseQuery)
            ->where('direction', BranchCashLedgerEntry::DIRECTION_OUT)
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->sum('amount'), 2);

        $systemEntriesCount = (clone $baseQuery)->where('is_system', true)->count();
        $manualEntriesCount = (clone $baseQuery)->where('is_system', false)->count();
        $latestOccurredAt = (clone $baseQuery)->max('occurred_at');

        return [
            'balance' => round($cashInTotal - $cashOutTotal, 2),
            'cash_in_total' => $cashInTotal,
            'cash_out_total' => $cashOutTotal,
            'month_cash_in' => $monthCashIn,
            'month_cash_out' => $monthCashOut,
            'month_net' => round($monthCashIn - $monthCashOut, 2),
            'system_entries_count' => $systemEntriesCount,
            'manual_entries_count' => $manualEntriesCount,
            'latest_occurred_at' => $latestOccurredAt ? Carbon::parse($latestOccurredAt)->toISOString() : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEntries(Branch $branch, int $limit = 60): array
    {
        return $branch->cashLedgerEntries()
            ->withTrashed()
            ->with('createdBy:id,name')
            ->limit($limit)
            ->get()
            ->map(fn (BranchCashLedgerEntry $entry) => $this->serializeEntry($entry))
            ->all();
    }

    public function createManualEntry(Branch $branch, array $data, int $createdBy): BranchCashLedgerEntry
    {
        $entry = new BranchCashLedgerEntry([
            'branch_id' => $branch->id,
            'entry_type' => BranchCashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
            'direction' => $data['direction'],
            'amount' => round((float) $data['amount'], 2),
            'occurred_at' => $data['occurred_at'],
            'title' => trim((string) $data['title']),
            'description' => $data['description'] ?? null,
            'metadata' => [],
            'is_system' => false,
            'created_by' => $createdBy,
        ]);

        $entry->save();

        return $entry->fresh(['createdBy:id,name']);
    }

    public function updateManualEntry(BranchCashLedgerEntry $entry, array $data): BranchCashLedgerEntry
    {
        $entry->fill([
            'direction' => $data['direction'],
            'amount' => round((float) $data['amount'], 2),
            'occurred_at' => $data['occurred_at'],
            'title' => trim((string) $data['title']),
            'description' => $data['description'] ?? null,
        ]);

        $entry->save();

        return $entry->fresh(['createdBy:id,name']);
    }

    public function deleteManualEntry(BranchCashLedgerEntry $entry): void
    {
        $entry->delete();
    }

    public function syncWalkIn(WalkIn $walkIn): ?BranchCashLedgerEntry
    {
        $walkIn->loadMissing('ratePlan:id,name');

        if (($walkIn->payment_method ?? 'cash') !== SaleTransaction::PAYMENT_METHOD_CASH) {
            $this->deleteSystemEntry(BranchCashLedgerEntry::TYPE_WALK_IN_SALE, $walkIn->id);

            return null;
        }

        return $this->syncSystemEntry(
            entryType: BranchCashLedgerEntry::TYPE_WALK_IN_SALE,
            sourceId: $walkIn->id,
            branchId: $walkIn->branch_id,
            direction: BranchCashLedgerEntry::DIRECTION_IN,
            amount: (float) $walkIn->amount_paid,
            occurredAt: $walkIn->visited_at,
            title: 'Walk-in payment',
            description: trim(collect([$walkIn->name, $walkIn->ratePlan?->name])->filter()->join(' · ')),
            metadata: [
                'walk_in_name' => $walkIn->name,
                'rate_plan_name' => $walkIn->ratePlan?->name,
                'payment_method' => $walkIn->payment_method ?? SaleTransaction::PAYMENT_METHOD_CASH,
            ],
        );
    }

    public function deleteWalkIn(WalkIn $walkIn): void
    {
        $this->deleteSystemEntry(BranchCashLedgerEntry::TYPE_WALK_IN_SALE, $walkIn->id);
    }

    public function syncSaleTransaction(SaleTransaction $saleTransaction): ?BranchCashLedgerEntry
    {
        $entryType = $this->saleEntryType($saleTransaction->type);

        if (! $entryType) {
            return null;
        }

        if ($saleTransaction->payment_method !== SaleTransaction::PAYMENT_METHOD_CASH) {
            $this->deleteSystemEntry($entryType, $saleTransaction->id);

            return null;
        }

        return $this->syncSystemEntry(
            entryType: $entryType,
            sourceId: $saleTransaction->id,
            branchId: $saleTransaction->branch_id,
            direction: BranchCashLedgerEntry::DIRECTION_IN,
            amount: (float) $saleTransaction->total,
            occurredAt: $saleTransaction->sold_at,
            title: $this->saleEntryTitle($saleTransaction->type),
            description: trim(collect([$saleTransaction->customer_name, $saleTransaction->item_name])->filter()->join(' · ')),
            metadata: [
                'sale_type' => $saleTransaction->type,
                'customer_name' => $saleTransaction->customer_name,
                'item_name' => $saleTransaction->item_name,
                'payment_method' => $saleTransaction->payment_method,
                'member_id' => $saleTransaction->member_id,
            ],
        );
    }

    public function deleteSaleTransaction(SaleTransaction $saleTransaction): void
    {
        $entryType = $this->saleEntryType($saleTransaction->type);

        if (! $entryType) {
            return;
        }

        $this->deleteSystemEntry($entryType, $saleTransaction->id);
    }

    public function syncPayout(Payout $payout): ?BranchCashLedgerEntry
    {
        $payout->loadMissing([
            'employee:id,name',
            'payroll:id,branch_id,period_start,period_end',
        ]);

        if (! $payout->payroll || $payout->method !== Payout::METHOD_CASH) {
            $this->deleteSystemEntry(BranchCashLedgerEntry::TYPE_PAYROLL_PAYOUT, $payout->id);

            return null;
        }

        $periodLabel = $payout->payroll->period_start && $payout->payroll->period_end
            ? $payout->payroll->period_start->format('Y-m-d').' – '.$payout->payroll->period_end->format('Y-m-d')
            : null;

        return $this->syncSystemEntry(
            entryType: BranchCashLedgerEntry::TYPE_PAYROLL_PAYOUT,
            sourceId: $payout->id,
            branchId: $payout->payroll->branch_id,
            direction: BranchCashLedgerEntry::DIRECTION_OUT,
            amount: (float) $payout->amount,
            occurredAt: $payout->paid_at,
            title: 'Payroll cash payout',
            description: trim(collect([$payout->employee?->name, $periodLabel])->filter()->join(' · ')),
            metadata: [
                'employee_name' => $payout->employee?->name,
                'method' => $payout->method,
                'payroll_id' => $payout->payroll_id,
                'payroll_period' => $periodLabel,
            ],
        );
    }

    public function syncCashAdvance(CashAdvance $cashAdvance): ?BranchCashLedgerEntry
    {
        $cashAdvance->loadMissing('employee:id,name');

        if (! in_array($cashAdvance->status, [
            CashAdvance::STATUS_RELEASED,
            CashAdvance::STATUS_PARTIALLY_PAID,
            CashAdvance::STATUS_PAID,
        ], true) || ! $cashAdvance->released_at) {
            $this->deleteSystemEntry(BranchCashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE, $cashAdvance->id);

            return null;
        }

        return $this->syncSystemEntry(
            entryType: BranchCashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE,
            sourceId: $cashAdvance->id,
            branchId: $cashAdvance->branch_id,
            direction: BranchCashLedgerEntry::DIRECTION_OUT,
            amount: (float) $cashAdvance->amount,
            occurredAt: $cashAdvance->released_at,
            title: 'Cash advance release',
            description: $cashAdvance->employee?->name,
            metadata: [
                'employee_name' => $cashAdvance->employee?->name,
                'status' => $cashAdvance->status,
                'remaining_amount' => (float) $cashAdvance->remaining_amount,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEntry(BranchCashLedgerEntry $entry): array
    {
        $entry->loadMissing('createdBy:id,name');

        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'entry_type_label' => $this->entryTypeLabel($entry->entry_type),
            'direction' => $entry->direction,
            'amount' => (float) $entry->amount,
            'signed_amount' => $entry->signedAmount(),
            'occurred_at' => $entry->occurred_at?->toISOString(),
            'deleted_at' => $entry->deleted_at?->toISOString(),
            'is_deleted' => $entry->trashed(),
            'title' => $entry->title,
            'description' => $entry->description,
            'metadata' => $entry->metadata ?? [],
            'is_system' => (bool) $entry->is_system,
            'created_by_name' => $entry->createdBy?->name,
            'created_at' => $entry->created_at?->toISOString(),
        ];
    }

    private function syncSystemEntry(
        string $entryType,
        int $sourceId,
        ?int $branchId,
        string $direction,
        float $amount,
        CarbonInterface|string|null $occurredAt,
        string $title,
        ?string $description = null,
        array $metadata = []
    ): ?BranchCashLedgerEntry {
        if (! $branchId || $amount <= 0) {
            $this->deleteSystemEntry($entryType, $sourceId);

            return null;
        }

        $entry = BranchCashLedgerEntry::withTrashed()->firstOrNew([
            'entry_type' => $entryType,
            'source_id' => $sourceId,
        ]);

        $entry->fill([
            'branch_id' => $branchId,
            'direction' => $direction,
            'amount' => round($amount, 2),
            'occurred_at' => $occurredAt ?? now(),
            'title' => $title,
            'description' => $description ?: null,
            'metadata' => $metadata,
            'is_system' => true,
            'created_by' => null,
        ]);

        if ($entry->trashed()) {
            $entry->restore();
        }

        $entry->save();

        return $entry->fresh(['createdBy:id,name']);
    }

    private function deleteSystemEntry(string $entryType, int $sourceId): void
    {
        BranchCashLedgerEntry::query()
            ->where('entry_type', $entryType)
            ->where('source_id', $sourceId)
            ->delete();
    }

    private function entryTypeLabel(string $entryType): string
    {
        return match ($entryType) {
            BranchCashLedgerEntry::TYPE_INVENTORY_SALE => 'Inventory Sale',
            BranchCashLedgerEntry::TYPE_MEMBERSHIP_SALE => 'Membership Sale',
            BranchCashLedgerEntry::TYPE_PT_PACKAGE_SALE => 'PT Package Sale',
            BranchCashLedgerEntry::TYPE_WALK_IN_SALE => 'Walk-in Sale',
            BranchCashLedgerEntry::TYPE_PAYROLL_PAYOUT => 'Payroll Payout',
            BranchCashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE => 'Cash Advance',
            default => 'Manual Adjustment',
        };
    }

    private function saleEntryType(string $saleType): ?string
    {
        return match ($saleType) {
            SaleTransaction::TYPE_INVENTORY => BranchCashLedgerEntry::TYPE_INVENTORY_SALE,
            SaleTransaction::TYPE_MEMBERSHIP => BranchCashLedgerEntry::TYPE_MEMBERSHIP_SALE,
            SaleTransaction::TYPE_PT_PACKAGE => BranchCashLedgerEntry::TYPE_PT_PACKAGE_SALE,
            default => null,
        };
    }

    private function saleEntryTitle(string $saleType): string
    {
        return match ($saleType) {
            SaleTransaction::TYPE_INVENTORY => 'Inventory sale',
            SaleTransaction::TYPE_MEMBERSHIP => 'Membership sale',
            SaleTransaction::TYPE_PT_PACKAGE => 'PT package sale',
            default => 'Sale',
        };
    }
}
