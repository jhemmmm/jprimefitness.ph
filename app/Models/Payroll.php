<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    const STATUS_DRAFT = 'draft';

    const STATUS_APPROVED = 'approved';

    const STATUS_CANCELED = 'canceled';

    const STATUS_PARTIALLY_PAID = 'partially_paid';

    const STATUS_PAID = 'paid';

    protected $fillable = [
        'employee_id',
        'branch_id',
        'period_start',
        'period_end',
        'gross_amount',
        'bonus',
        'pt_commission_amount',
        'pt_commission_items',
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
        'gross_amount' => 'decimal:2',
        'bonus' => 'decimal:2',
        'pt_commission_amount' => 'decimal:2',
        'pt_commission_items' => 'array',
        'income_tax' => 'decimal:2',
        'employee_contributions' => 'array',
        'employer_contributions' => 'array',
        'manual_deductions' => 'decimal:2',
        'cash_advance_deduction' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function ptCommissionPackages(): HasMany
    {
        return $this->hasMany(MemberPtPackage::class, 'commission_payroll_id');
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

    public function employeeContributionTotal(): float
    {
        return round(collect($this->employee_contributions ?? [])->sum(fn ($item) => (float) ($item['amount'] ?? 0)), 2);
    }

    public function employerContributionTotal(): float
    {
        return round(collect($this->employer_contributions ?? [])->sum(fn ($item) => (float) ($item['amount'] ?? 0)), 2);
    }

    public function employeeDeductionsTotal(): float
    {
        return round((float) $this->income_tax + $this->employeeContributionTotal() + (float) $this->manual_deductions, 2);
    }

    public function totalEarnings(): float
    {
        return round((float) $this->gross_amount + (float) $this->bonus + (float) $this->pt_commission_amount, 2);
    }
}
