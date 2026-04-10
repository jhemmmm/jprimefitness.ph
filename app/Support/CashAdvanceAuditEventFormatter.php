<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Str;

class CashAdvanceAuditEventFormatter
{
    /**
     * @param  array{id: int, employee_id: int, employee_name: string, amount: float}  $cashAdvance
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     subject_type: string,
     *     subject_id: int,
     *     subject_label: string,
     *     event: string,
     *     title: string,
     *     message: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function format(array $cashAdvance, string $event, array $metadata = []): array
    {
        $normalizedEvent = trim($event) !== '' ? $event : 'updated';

        return [
            'subject_type' => AuditEvent::SUBJECT_CASH_ADVANCE,
            'subject_id' => $cashAdvance['id'],
            'subject_label' => $this->subjectLabel($cashAdvance),
            'event' => $normalizedEvent,
            'title' => $this->title($normalizedEvent),
            'message' => $this->message($cashAdvance, $normalizedEvent, $metadata),
            'metadata' => $this->metadata($cashAdvance, $metadata),
        ];
    }

    public function eventLabel(string $event): string
    {
        return Str::of($event)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * @param  array{id: int, employee_id: int, employee_name: string, amount: float}  $cashAdvance
     */
    private function subjectLabel(array $cashAdvance): string
    {
        return sprintf('Cash Advance #%d - %s', $cashAdvance['id'], $cashAdvance['employee_name']);
    }

    private function title(string $event): string
    {
        return match ($event) {
            'requested' => 'Cash advance requested',
            'approved' => 'Cash advance approved',
            'released' => 'Cash advance released',
            'partially_paid' => 'Cash advance partially paid',
            'paid' => 'Cash advance paid',
            'cancelled' => 'Cash advance cancelled',
            'deleted' => 'Cash advance deleted',
            'restored' => 'Cash advance restored',
            default => 'Cash advance updated',
        };
    }

    /**
     * @param  array{id: int, employee_id: int, employee_name: string, amount: float}  $cashAdvance
     * @param  array<string, mixed>  $metadata
     */
    private function message(array $cashAdvance, string $event, array $metadata): string
    {
        $employeeName = $cashAdvance['employee_name'];
        $amount = $this->currency($cashAdvance['amount']);

        $message = match ($event) {
            'requested' => $employeeName.' requested a cash advance of '.$amount.'.',
            'approved' => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was approved.',
            'released' => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was released.',
            'cancelled' => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was cancelled.',
            'partially_paid' => $this->deductionMessage($cashAdvance, $event, $metadata),
            'paid' => $this->deductionMessage($cashAdvance, $event, $metadata),
            'deleted' => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was deleted.',
            'restored' => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was restored.',
            default => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was updated.',
        };

        $notes = trim((string) ($metadata['notes'] ?? ''));

        if ($notes !== '') {
            $message .= ' Notes: '.$notes;
        }

        return $message;
    }

    /**
     * @param  array{id: int, employee_id: int, employee_name: string, amount: float}  $cashAdvance
     * @param  array<string, mixed>  $metadata
     */
    private function deductionMessage(array $cashAdvance, string $event, array $metadata): string
    {
        $employeeName = $cashAdvance['employee_name'];
        $amount = $this->currency($cashAdvance['amount']);
        $payrollSuffix = ($metadata['source'] ?? null) === 'payroll' && ! empty($metadata['source_id'])
            ? ' via payroll #'.$metadata['source_id']
            : '';

        if (isset($metadata['deducted_amount']) && $metadata['deducted_amount'] !== null) {
            $deductedAmount = $this->currency((float) $metadata['deducted_amount']);

            if ($event === 'paid' && isset($metadata['remaining_after']) && (float) $metadata['remaining_after'] <= 0) {
                return 'A payroll deduction of '.$deductedAmount.' fully settled '.$employeeName."'s cash advance of ".$amount.$payrollSuffix.'.';
            }

            $message = 'A payroll deduction of '.$deductedAmount.' was applied to '.$employeeName."'s cash advance of ".$amount.$payrollSuffix.'.';

            if (isset($metadata['remaining_after']) && $metadata['remaining_after'] !== null) {
                $message .= ' Remaining balance: '.$this->currency((float) $metadata['remaining_after']).'.';
            }

            return $message;
        }

        return match ($event) {
            'paid' => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was fully paid.',
            default => 'The cash advance for '.$employeeName.' amounting to '.$amount.' was partially paid.',
        };
    }

    /**
     * @param  array{id: int, employee_id: int, employee_name: string, amount: float}  $cashAdvance
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function metadata(array $cashAdvance, array $metadata): array
    {
        return array_filter([
            'employee_id' => $cashAdvance['employee_id'],
            'employee_name' => $cashAdvance['employee_name'],
            'amount' => round((float) $cashAdvance['amount'], 2),
            'source' => $metadata['source'] ?? null,
            'source_id' => $metadata['source_id'] ?? null,
            'notes' => $metadata['notes'] ?? null,
            'deducted_amount' => isset($metadata['deducted_amount']) ? round((float) $metadata['deducted_amount'], 2) : null,
            'remaining_before' => isset($metadata['remaining_before']) ? round((float) $metadata['remaining_before'], 2) : null,
            'remaining_after' => isset($metadata['remaining_after']) ? round((float) $metadata['remaining_after'], 2) : null,
        ], static function (mixed $value): bool {
            return $value !== null && $value !== '';
        });
    }

    private function currency(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }
}
