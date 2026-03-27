<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    const METHOD_CASH = 'cash';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ONLINE_PAYMENT = 'online_payment';


    protected $fillable = [
        'payroll_id',
        'employee_id',
        'amount',
        'method',
        'reference_number',
        'released_by',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'paid_at'  => 'datetime',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
