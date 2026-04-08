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
        'gross_amount',
        'bonus',
        'income_tax',
        'pt_commission_amount',
        'pt_commission_items',
        'membership_commission_amount',
        'membership_commission_items',
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
        'gross_amount' => 'decimal:2',
        'bonus' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'pt_commission_amount' => 'decimal:2',
        'pt_commission_items' => 'array',
        'membership_commission_amount' => 'decimal:2',
        'membership_commission_items' => 'array',
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

    public function ptCommissionPackages(): HasMany
    {
        return $this->hasMany(MemberPtPackage::class, 'commission_payroll_id');
    }

    public function membershipCommissionSubscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class, 'commission_payroll_id');
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
        return round((float) $this->income_tax + (float) $this->manual_deductions, 2);
    }

    public function totalEarnings(): float
    {
        return round(
            (float) $this->gross_amount
            + (float) $this->bonus
            + (float) $this->pt_commission_amount
            + (float) $this->membership_commission_amount,
            2
        );
    }

    public function taxableEarnings(): float
    {
        return $this->totalEarnings();
    }
}
