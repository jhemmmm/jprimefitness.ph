<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_id',
        'pay_frequency',
        'period_start',
        'period_end',
        'regular_hours',
        'regular_pay_amount',
        'overwork_hours',
        'overwork_pay_amount',
        'gross_amount',
        'bonus',
        'income_tax',
        'employee_contributions',
        'employer_contributions',
        'manual_deductions',
        'cash_advance_deduction',
        'net_amount',
        'status',
        'notes',
        'generated_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
        'pay_frequency' => 'string',
        'regular_hours' => 'decimal:2',
        'regular_pay_amount' => 'decimal:2',
        'overwork_hours' => 'decimal:2',
        'overwork_pay_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'bonus' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'manual_deductions' => 'decimal:2',
        'cash_advance_deduction' => 'decimal:2',
        'employee_contributions' => 'array',
        'employer_contributions' => 'array',
        'net_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function totalPaid(): float
    {
        return (float) $this->payouts()->sum('amount');
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->net_amount - $this->totalPaid());
    }

    public function employeeDeductionsTotal(): float
    {
        return round(
            (float) $this->income_tax
            + (float) $this->manual_deductions
            + $this->employeeContributionsTotal(),
            2
        );
    }

    public function employeeContributionsTotal(): float
    {
        return round(
            collect($this->employee_contributions ?? [])
                ->sum(fn (array $program): float => round((float) ($program['total'] ?? 0), 2)),
            2
        );
    }

    public function employerContributionsTotal(): float
    {
        return round(
            collect($this->employer_contributions ?? [])
                ->sum(fn (array $program): float => round((float) ($program['total'] ?? 0), 2)),
            2
        );
    }

    public function hasAttendanceBreakdownSnapshot(): bool
    {
        return $this->regular_hours !== null
            || $this->regular_pay_amount !== null
            || $this->overwork_hours !== null
            || $this->overwork_pay_amount !== null;
    }

    public function manualGrossAdjustmentAmount(): ?float
    {
        if (! $this->hasAttendanceBreakdownSnapshot()) {
            return null;
        }

        return round(
            (float) $this->gross_amount
            - (float) $this->regular_pay_amount
            - (float) $this->overwork_pay_amount,
            2
        );
    }

    public function totalEarnings(): float
    {
        return round(
            (float) $this->gross_amount
            + (float) $this->bonus,
            2
        );
    }

    public function taxableEarnings(): float
    {
        return $this->totalEarnings();
    }
}
