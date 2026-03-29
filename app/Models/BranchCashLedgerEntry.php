<?php

namespace App\Models;

use Database\Factories\BranchCashLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BranchCashLedgerEntry extends Model
{
    /** @use HasFactory<BranchCashLedgerEntryFactory> */
    use HasFactory, SoftDeletes;

    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const TYPE_INVENTORY_SALE = 'inventory_sale';

    public const TYPE_MEMBERSHIP_SALE = 'membership_sale';

    public const TYPE_PT_PACKAGE_SALE = 'pt_package_sale';

    public const TYPE_WALK_IN_SALE = 'walk_in_sale';

    public const TYPE_PAYROLL_PAYOUT = 'payroll_payout';

    public const TYPE_CASH_ADVANCE_RELEASE = 'cash_advance_release';

    protected $fillable = [
        'branch_id',
        'entry_type',
        'direction',
        'source_id',
        'amount',
        'occurred_at',
        'title',
        'description',
        'metadata',
        'is_system',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
        'metadata' => 'array',
        'is_system' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === self::DIRECTION_OUT
            ? -1 * $amount
            : $amount;
    }
}
