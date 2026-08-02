<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAdvance extends Model
{
    use HasFactory;
    use SyncsToOutbox;

    public const METHOD_CASH = 'cash';

    public const METHOD_GCASH = 'gcash';

    public const METHOD_ONLINE_PAYMENT = 'online_payment';

    protected $fillable = [
        'employee_id',
        'amount',
        'repaid_amount',
        'method',
        'reference_number',
        'released_by',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'repaid_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(CashAdvanceRepayment::class);
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount - (float) $this->repaid_amount, 2));
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereColumn('repaid_amount', '<', 'amount');
    }
}
