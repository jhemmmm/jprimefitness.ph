<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\CashAdvance;
use App\Models\CashLedgerEntry;
use App\Models\Payout;
use App\Models\SaleTransaction;
use App\Models\WalkIn;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CashLedgerService
{
    public function __construct(
        private AuditHistoryService $auditHistoryService,
    ) {}

    /**
     * @return array<string, float|int|string|null>
     */
    public function summarize(): array
    {
        $baseQuery = CashLedgerEntry::query();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $cashInTotal = round((clone $baseQuery)
            ->where('direction', CashLedgerEntry::DIRECTION_IN)
            ->sum('amount'), 2);

        $cashOutTotal = round((clone $baseQuery)
            ->where('direction', CashLedgerEntry::DIRECTION_OUT)
            ->sum('amount'), 2);

        $monthCashIn = round((clone $baseQuery)
            ->where('direction', CashLedgerEntry::DIRECTION_IN)
            ->whereBetween('occurred_at', [$monthStart, $monthEnd])
            ->sum('amount'), 2);

        $monthCashOut = round((clone $baseQuery)
            ->where('direction', CashLedgerEntry::DIRECTION_OUT)
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
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function listEntries(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->filteredEntriesQuery($filters)
            ->with('createdBy:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (CashLedgerEntry $entry) => $this->serializeEntry($entry));
    }

    public function createManualEntry(array $data, int $createdBy): CashLedgerEntry
    {
        $entry = new CashLedgerEntry([
            'entry_type' => CashLedgerEntry::TYPE_MANUAL_ADJUSTMENT,
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

        $entry = $entry->fresh(['createdBy:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            $entry->id,
            'created',
            $this->cashLedgerAuditSnapshot($entry),
            [],
            auth()->id(),
            auth()->user()?->name,
            $entry->occurred_at ?? now(),
        );

        return $entry;
    }

    public function updateManualEntry(CashLedgerEntry $entry, array $data): CashLedgerEntry
    {
        $entry->fill([
            'direction' => $data['direction'],
            'amount' => round((float) $data['amount'], 2),
            'occurred_at' => $data['occurred_at'],
            'title' => trim((string) $data['title']),
            'description' => $data['description'] ?? null,
        ]);

        $entry->save();

        $entry = $entry->fresh(['createdBy:id,name']);

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            $entry->id,
            'updated',
            $this->cashLedgerAuditSnapshot($entry),
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );

        return $entry;
    }

    public function deleteManualEntry(CashLedgerEntry $entry): void
    {
        $snapshot = $this->cashLedgerAuditSnapshot($entry);

        $entry->delete();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            (int) $entry->id,
            'deleted',
            $snapshot,
            [],
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
    }

    public function syncWalkIn(WalkIn $walkIn, string $causeEvent = 'updated'): ?CashLedgerEntry
    {
        $walkIn->loadMissing('ratePlan:id,name');
        $causedBy = $this->auditHistoryService->causedBy(
            AuditEvent::SUBJECT_WALK_IN,
            $walkIn->id,
            $causeEvent,
            $this->walkInAuditSnapshot($walkIn),
        );

        if (($walkIn->payment_method ?? 'cash') !== SaleTransaction::PAYMENT_METHOD_CASH) {
            $this->deleteSystemEntry(CashLedgerEntry::TYPE_WALK_IN_SALE, $walkIn->id, $causedBy);

            return null;
        }

        return $this->syncSystemEntry(
            entryType: CashLedgerEntry::TYPE_WALK_IN_SALE,
            sourceId: $walkIn->id,
            direction: CashLedgerEntry::DIRECTION_IN,
            amount: (float) $walkIn->amount_paid,
            occurredAt: $walkIn->visited_at,
            title: 'Walk-in payment',
            description: trim(collect([$walkIn->name, $walkIn->ratePlan?->name])->filter()->join(' · ')),
            metadata: [
                'walk_in_name' => $walkIn->name,
                'rate_plan_name' => $walkIn->ratePlan?->name,
                'payment_method' => $walkIn->payment_method ?? SaleTransaction::PAYMENT_METHOD_CASH,
            ],
            causedBy: $causedBy,
        );
    }

    public function deleteWalkIn(WalkIn $walkIn): void
    {
        $this->deleteSystemEntry(
            CashLedgerEntry::TYPE_WALK_IN_SALE,
            $walkIn->id,
            $this->auditHistoryService->causedBy(
                AuditEvent::SUBJECT_WALK_IN,
                $walkIn->id,
                'deleted',
                $this->walkInAuditSnapshot($walkIn),
            ),
        );
    }

    public function syncSaleTransaction(SaleTransaction $saleTransaction): ?CashLedgerEntry
    {
        $entryType = $this->saleEntryType($saleTransaction->type);

        if (! $entryType) {
            return null;
        }

        if ($saleTransaction->payment_method !== SaleTransaction::PAYMENT_METHOD_CASH) {
            $this->deleteSystemEntry(
                $entryType,
                $saleTransaction->id,
                $this->auditHistoryService->causedBy(
                    AuditEvent::SUBJECT_SALE_TRANSACTION,
                    $saleTransaction->id,
                    'created',
                    $this->saleTransactionAuditSnapshot($saleTransaction),
                    [
                        'member_id' => $saleTransaction->member_id,
                    ],
                ),
            );

            return null;
        }

        return $this->syncSystemEntry(
            entryType: $entryType,
            sourceId: $saleTransaction->id,
            direction: CashLedgerEntry::DIRECTION_IN,
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
            causedBy: $this->auditHistoryService->causedBy(
                AuditEvent::SUBJECT_SALE_TRANSACTION,
                $saleTransaction->id,
                'created',
                $this->saleTransactionAuditSnapshot($saleTransaction),
                [
                    'member_id' => $saleTransaction->member_id,
                ],
            ),
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

    public function syncPayout(Payout $payout): ?CashLedgerEntry
    {
        $payout->loadMissing([
            'employee:id,name',
            'payroll:id,period_start,period_end',
        ]);

        if (! $payout->payroll || $payout->method !== Payout::METHOD_CASH) {
            $this->deleteSystemEntry(
                CashLedgerEntry::TYPE_PAYROLL_PAYOUT,
                $payout->id,
                $this->auditHistoryService->causedBy(
                    AuditEvent::SUBJECT_PAYOUT,
                    $payout->id,
                    'created',
                    $this->payoutAuditSnapshot($payout),
                    [
                        'employee_id' => $payout->employee_id,
                    ],
                ),
            );

            return null;
        }

        $periodLabel = $payout->payroll->period_start && $payout->payroll->period_end
            ? $payout->payroll->period_start->format('Y-m-d').' – '.$payout->payroll->period_end->format('Y-m-d')
            : null;

        return $this->syncSystemEntry(
            entryType: CashLedgerEntry::TYPE_PAYROLL_PAYOUT,
            sourceId: $payout->id,
            direction: CashLedgerEntry::DIRECTION_OUT,
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
            causedBy: $this->auditHistoryService->causedBy(
                AuditEvent::SUBJECT_PAYOUT,
                $payout->id,
                'created',
                $this->payoutAuditSnapshot($payout, $periodLabel),
                [
                    'employee_id' => $payout->employee_id,
                ],
            ),
        );
    }

    public function syncCashAdvance(CashAdvance $cashAdvance, ?string $causeEvent = null): ?CashLedgerEntry
    {
        $cashAdvance->loadMissing('employee:id,name');
        $resolvedCauseEvent = $causeEvent ?? (string) $cashAdvance->status;
        $causedBy = $this->auditHistoryService->causedBy(
            AuditEvent::SUBJECT_CASH_ADVANCE,
            $cashAdvance->id,
            $resolvedCauseEvent,
            $this->cashAdvanceAuditSnapshot($cashAdvance),
            [
                'employee_id' => $cashAdvance->employee_id,
            ],
        );

        if (! in_array($cashAdvance->status, [
            CashAdvance::STATUS_RELEASED,
            CashAdvance::STATUS_PARTIALLY_PAID,
            CashAdvance::STATUS_PAID,
        ], true) || ! $cashAdvance->released_at) {
            $this->deleteSystemEntry(CashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE, $cashAdvance->id, $causedBy);

            return null;
        }

        return $this->syncSystemEntry(
            entryType: CashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE,
            sourceId: $cashAdvance->id,
            direction: CashLedgerEntry::DIRECTION_OUT,
            amount: (float) $cashAdvance->amount,
            occurredAt: $cashAdvance->released_at,
            title: 'Cash advance release',
            description: $cashAdvance->employee?->name,
            metadata: [
                'employee_name' => $cashAdvance->employee?->name,
                'status' => $cashAdvance->status,
                'remaining_amount' => (float) $cashAdvance->remaining_amount,
            ],
            causedBy: $causedBy,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEntry(CashLedgerEntry $entry): array
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

    private function filteredEntriesQuery(array $filters): Builder
    {
        return CashLedgerEntry::query()
            ->withTrashed()
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $dateFrom) => $query->whereDate('occurred_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $dateTo) => $query->whereDate('occurred_at', '<=', $dateTo))
            ->when($filters['direction'] ?? null, fn (Builder $query, string $direction) => $query->where('direction', $direction))
            ->when($filters['entry_type'] ?? null, fn (Builder $query, string $entryType) => $query->where('entry_type', $entryType))
            ->when(($filters['mode'] ?? null) === 'system', fn (Builder $query) => $query->where('is_system', true))
            ->when(($filters['mode'] ?? null) === 'manual', fn (Builder $query) => $query->where('is_system', false))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $term = trim($search);

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            });
    }

    private function syncSystemEntry(
        string $entryType,
        int $sourceId,
        string $direction,
        float $amount,
        CarbonInterface|string|null $occurredAt,
        string $title,
        ?string $description = null,
        array $metadata = [],
        ?array $causedBy = null,
    ): ?CashLedgerEntry {
        if ($amount <= 0) {
            $this->deleteSystemEntry($entryType, $sourceId, $causedBy);

            return null;
        }

        $entry = CashLedgerEntry::withTrashed()->firstOrNew([
            'entry_type' => $entryType,
            'source_id' => $sourceId,
        ]);
        $wasMissing = ! $entry->exists;
        $wasTrashed = $entry->trashed();

        $entry->fill([
            'direction' => $direction,
            'amount' => round($amount, 2),
            'occurred_at' => $occurredAt ?? now(),
            'title' => $title,
            'description' => $description ?: null,
            'metadata' => $metadata,
            'is_system' => true,
            'created_by' => null,
        ]);

        if (! $wasMissing && ! $wasTrashed && ! $entry->isDirty()) {
            return $entry->fresh(['createdBy:id,name']);
        }

        if ($wasTrashed) {
            $entry->restore();
        }

        $entry->save();

        $entry = $entry->fresh(['createdBy:id,name']);
        $event = ($wasMissing || $wasTrashed) ? 'created' : 'updated';

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            $entry->id,
            $event,
            $this->cashLedgerAuditSnapshot($entry),
            $this->metadataWithCausedBy([], $causedBy),
            auth()->id(),
            auth()->user()?->name,
            $event === 'created' ? ($entry->occurred_at ?? now()) : now(),
        );

        return $entry;
    }

    private function deleteSystemEntry(string $entryType, int $sourceId, ?array $causedBy = null): void
    {
        $entry = CashLedgerEntry::query()
            ->where('entry_type', $entryType)
            ->where('source_id', $sourceId)
            ->first();

        if (! $entry) {
            return;
        }

        $snapshot = $this->cashLedgerAuditSnapshot($entry);
        $entry->delete();

        $this->auditHistoryService->recordSubjectEvent(
            AuditEvent::SUBJECT_CASH_LEDGER_ENTRY,
            (int) $entry->id,
            'deleted',
            $snapshot,
            $this->metadataWithCausedBy([], $causedBy),
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
    }

    private function entryTypeLabel(string $entryType): string
    {
        return match ($entryType) {
            CashLedgerEntry::TYPE_INVENTORY_SALE => 'Inventory Sale',
            CashLedgerEntry::TYPE_MEMBERSHIP_SALE => 'Membership Sale',
            CashLedgerEntry::TYPE_PT_PACKAGE_SALE => 'PT Package Sale',
            CashLedgerEntry::TYPE_WALK_IN_SALE => 'Walk-in Sale',
            CashLedgerEntry::TYPE_PAYROLL_PAYOUT => 'Payroll Payout',
            CashLedgerEntry::TYPE_CASH_ADVANCE_RELEASE => 'Cash Advance',
            default => 'Manual Adjustment',
        };
    }

    private function saleEntryType(string $saleType): ?string
    {
        return match ($saleType) {
            SaleTransaction::TYPE_INVENTORY => CashLedgerEntry::TYPE_INVENTORY_SALE,
            SaleTransaction::TYPE_MEMBERSHIP => CashLedgerEntry::TYPE_MEMBERSHIP_SALE,
            SaleTransaction::TYPE_PT_PACKAGE => CashLedgerEntry::TYPE_PT_PACKAGE_SALE,
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

    /**
     * @return array<string, mixed>
     */
    private function cashLedgerAuditSnapshot(CashLedgerEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'entry_type' => $entry->entry_type,
            'direction' => $entry->direction,
            'amount' => round((float) $entry->amount, 2),
            'title' => $entry->title,
            'description' => $entry->description,
            'is_system' => (bool) $entry->is_system,
            'source_id' => $entry->source_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function saleTransactionAuditSnapshot(SaleTransaction $saleTransaction): array
    {
        return [
            'id' => $saleTransaction->id,
            'member_id' => $saleTransaction->member_id,
            'type' => $saleTransaction->type,
            'customer_name' => $saleTransaction->customer_name,
            'item_name' => $saleTransaction->item_name,
            'payment_method' => $saleTransaction->payment_method,
            'total' => round((float) $saleTransaction->total, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function walkInAuditSnapshot(WalkIn $walkIn): array
    {
        return [
            'id' => $walkIn->id,
            'name' => $walkIn->name,
            'rate_plan_name' => $walkIn->ratePlan?->name,
            'amount_paid' => round((float) $walkIn->amount_paid, 2),
            'payment_method' => $walkIn->payment_method,
            'served_by' => $walkIn->served_by,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payoutAuditSnapshot(Payout $payout, ?string $payrollPeriod = null): array
    {
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

    /**
     * @return array{id: int, employee_id: int, employee_name: string, amount: float}
     */
    private function cashAdvanceAuditSnapshot(CashAdvance $cashAdvance): array
    {
        return [
            'id' => $cashAdvance->id,
            'employee_id' => $cashAdvance->employee_id,
            'employee_name' => $cashAdvance->employee?->name ?? 'Unknown Employee',
            'amount' => round((float) $cashAdvance->amount, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>|null  $causedBy
     * @return array<string, mixed>
     */
    private function metadataWithCausedBy(array $metadata, ?array $causedBy): array
    {
        if ($causedBy === null) {
            return $metadata;
        }

        return [
            ...$metadata,
            'caused_by' => $causedBy,
        ];
    }
}
