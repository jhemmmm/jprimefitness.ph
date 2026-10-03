<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashAdvanceRepayment extends Model
{
    use SyncsToOutbox;

    protected $fillable = [
        'cash_advance_id',
        'payroll_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function cashAdvance(): BelongsTo
    {
        return $this->belongsTo(CashAdvance::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
