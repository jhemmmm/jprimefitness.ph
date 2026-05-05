<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;

class KioskPayment extends Model
{
    use SyncsToOutbox;

    public function syncsUuid(): bool
    {
        return false;
    }

    public function syncEntityKey(): string
    {
        return (string) $this->getAttribute('reference');
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const DISCOUNT_STUDENT = 'student';

    public const DISCOUNT_SENIOR = 'senior';

    public const DISCOUNT_PERCENT = 20;

    protected $fillable = [
        'reference',
        'name',
        'phone',
        'amount',
        'base_amount',
        'discount_type',
        'status',
        'qr_data',
        'paymongo_payment_intent_id',
        'expires_at',
        'paid_at',
        'consumed_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'base_amount' => 'float',
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

    public function isOnline(): bool
    {
        return $this->paymongo_payment_intent_id !== null;
    }

    public static function findForCli(?string $reference, bool $requirePaymongoIntent = false): ?self
    {
        if ($reference !== null) {
            return self::query()->where('reference', $reference)->first();
        }

        $query = self::query()
            ->where('status', self::STATUS_PENDING)
            ->latest('id');

        if ($requirePaymongoIntent) {
            $query->whereNotNull('paymongo_payment_intent_id');
        }

        return $query->first();
    }

}
