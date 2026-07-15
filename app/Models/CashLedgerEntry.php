<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashLedgerEntry extends Model
{
    use HasFactory, SyncsToOutbox;

    public const TYPE_SALE = 'sale';

    public const TYPE_SALE_VOID = 'sale_void';

    public const TYPE_EXPENSE = 'expense';

    public const TYPE_PAYOUT = 'payout';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const CATEGORIES = [
        'rent',
        'utilities',
        'supplies',
        'equipment',
        'inventory_restock',
        'salary',
        'miscellaneous',
    ];

    protected $fillable = [
        'uuid',
        'session_id',
        'type',
        'category',
        'amount',
        'description',
        'notes',
        'receipt_path',
        'source_type',
        'source_id',
        'recorded_by',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashDrawerSession::class, 'session_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
