<?php

namespace App\Services;

use App\Models\CashDrawerSession;
use App\Models\CashLedgerEntry;
use App\Models\SystemActivity;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashDrawerService
{
    public function __construct(
        private SystemActivityService $systemActivityService,
    ) {}

    public function currentSession(): ?CashDrawerSession
    {
        return CashDrawerSession::query()->where('is_open', true)->first();
    }

    public function expectedCash(CashDrawerSession $session): float
    {
        return round((float) $session->opening_float + (float) $session->entries()->sum('amount'), 2);
    }

    /**
     * Suggested opening float = what stayed in the drawer after the last close.
     */
    public function suggestedFloat(): ?float
    {
        $lastClosed = CashDrawerSession::query()
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->first(['counted_cash', 'deposited_amount']);

        if (! $lastClosed) {
            return null;
        }

        return round((float) $lastClosed->counted_cash - (float) $lastClosed->deposited_amount, 2);
    }

    public function openSession(float $openingFloat, User $openedBy, ?string $notes = null): CashDrawerSession
    {
        try {
            $session = CashDrawerSession::create([
                'is_open' => true,
                'opening_float' => round($openingFloat, 2),
                'opened_by' => $openedBy->id,
                'opened_at' => now(),
                'notes' => $notes,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'opening_float' => ['A drawer session is already open. Close it before opening a new one.'],
            ]);
        }

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_CASH_DRAWER_SESSION,
            $session->id,
            'opened',
            ['opening_float' => (float) $session->opening_float],
            [],
            $openedBy->id,
            $openedBy->name,
            now(),
        );

        return $session;
    }

    public function closeSession(
        CashDrawerSession $session,
        float $countedCash,
        float $depositedAmount,
        ?string $depositReference,
        User $closedBy
    ): CashDrawerSession {
        $session = DB::transaction(function () use ($session, $countedCash, $depositedAmount, $depositReference, $closedBy) {
            $session = CashDrawerSession::query()->lockForUpdate()->findOrFail($session->id);

            abort_unless((bool) $session->is_open, 409, 'This drawer session is already closed.');

            $expected = $this->expectedCash($session);

            $session->forceFill([
                'is_open' => null,
                'closed_by' => $closedBy->id,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => round($countedCash, 2),
                'over_short' => round($countedCash - $expected, 2),
                'deposited_amount' => round($depositedAmount, 2),
                'deposit_reference' => $depositReference,
            ])->save();

            return $session;
        });

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_CASH_DRAWER_SESSION,
            $session->id,
            'closed',
            [
                'expected_cash' => (float) $session->expected_cash,
                'counted_cash' => (float) $session->counted_cash,
                'over_short' => (float) $session->over_short,
                'deposited_amount' => (float) $session->deposited_amount,
            ],
            [],
            $closedBy->id,
            $closedBy->name,
            now(),
        );

        return $session;
    }

    /**
     * @param  array{category: string, description: string, amount: float|int|string, notes?: ?string, receipt_path?: ?string}  $data
     */
    public function recordExpense(array $data, User $recordedBy): CashLedgerEntry
    {
        $entry = CashLedgerEntry::create([
            'session_id' => $this->currentSession()?->id,
            'type' => CashLedgerEntry::TYPE_EXPENSE,
            'category' => $data['category'],
            'amount' => -round(abs((float) $data['amount']), 2),
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'recorded_by' => $recordedBy->id,
            'occurred_at' => now(),
        ]);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_CASH_LEDGER_ENTRY,
            $entry->id,
            'created',
            [
                'type' => $entry->type,
                'category' => $entry->category,
                'amount' => (float) $entry->amount,
                'description' => $entry->description,
            ],
            [],
            $recordedBy->id,
            $recordedBy->name,
            now(),
        );

        return $entry;
    }

    /**
     * Manual paid-in/paid-out correction. Positive puts cash in, negative takes it out.
     *
     * @param  array{description: string, amount: float|int|string, notes?: ?string}  $data
     */
    public function recordAdjustment(array $data, User $recordedBy): CashLedgerEntry
    {
        $entry = CashLedgerEntry::create([
            'session_id' => $this->currentSession()?->id,
            'type' => CashLedgerEntry::TYPE_ADJUSTMENT,
            'amount' => round((float) $data['amount'], 2),
            'description' => $data['description'],
            'notes' => $data['notes'] ?? null,
            'recorded_by' => $recordedBy->id,
            'occurred_at' => now(),
        ]);

        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_CASH_LEDGER_ENTRY,
            $entry->id,
            'created',
            [
                'type' => $entry->type,
                'amount' => (float) $entry->amount,
                'description' => $entry->description,
            ],
            [],
            $recordedBy->id,
            $recordedBy->name,
            now(),
        );

        return $entry;
    }

    /**
     * Automatic entry from a sale/payout. Idempotent per (source_type, source_id, type),
     * backed by the unique index. Never reads auth() — kiosk sales have no user.
     */
    public function recordSourceEntry(
        string $type,
        float $signedAmount,
        string $sourceType,
        int $sourceId,
        string $description,
        ?int $recordedById,
        DateTimeInterface|string|null $occurredAt
    ): CashLedgerEntry {
        return CashLedgerEntry::firstOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'type' => $type,
            ],
            [
                'session_id' => $this->currentSession()?->id,
                'amount' => round($signedAmount, 2),
                'description' => $description,
                'recorded_by' => $recordedById,
                'occurred_at' => $occurredAt ?? now(),
            ],
        );
    }
}
