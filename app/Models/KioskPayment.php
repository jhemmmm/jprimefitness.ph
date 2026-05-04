<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskPayment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'reference',
        'name',
        'phone',
        'amount_centavos',
        'status',
        'qr_data',
        'expires_at',
        'paid_at',
        'consumed_at',
    ];

    protected $casts = [
        'amount_centavos' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    public function getAmountPesos(): float
    {
        return $this->amount_centavos / 100;
    }
}
