<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashDrawerSession extends Model
{
    use HasFactory, SyncsToOutbox;

    protected $fillable = [
        'uuid',
        'is_open',
        'opening_float',
        'opened_by',
        'opened_at',
        'closed_by',
        'closed_at',
        'expected_cash',
        'counted_cash',
        'over_short',
        'deposited_amount',
        'deposit_reference',
        'notes',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'counted_cash' => 'decimal:2',
        'over_short' => 'decimal:2',
        'deposited_amount' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CashLedgerEntry::class, 'session_id');
    }
}
