<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    protected $fillable = [
        'employee_id', 'branch_id', 'period_start', 'period_end',
        'gross_amount', 'bonus', 'manual_deductions', 'cash_advance_deduction', 'net_amount',
        'status', 'notes', 'generated_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'period_start'             => 'date',
        'period_end'               => 'date',
        'approved_at'              => 'datetime',
        'gross_amount'             => 'decimal:2',
        'bonus'                    => 'decimal:2',
        'manual_deductions'        => 'decimal:2',
        'cash_advance_deduction'   => 'decimal:2',
        'net_amount'               => 'decimal:2',
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
}
