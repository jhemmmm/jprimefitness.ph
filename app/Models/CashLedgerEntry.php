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

    public const TYPE_CASH_ADVANCE = 'cash_advance';

    public const TYPE_CASH_ADVANCE_VOID = 'cash_advance_void';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_ONLINE_PAYMENT = 'online_payment';

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
        'payment_method',
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

    protected $attributes = [
        'payment_method' => self::PAYMENT_METHOD_CASH,
    ];

    /**
     * Return the payment methods supported for recorded expenses.
     *
     * @return array<int, string>
     */
    public static function supportedPaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_CASH,
            self::PAYMENT_METHOD_ONLINE_PAYMENT,
        ];
    }

    /**
     * Return the user-facing payment method label.
     *
     * @return string
     */
    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_METHOD_ONLINE_PAYMENT => 'Online Payment',
            default => 'Cash',
        };
    }

    /**
     * Determine whether the entry changes the physical drawer balance.
     *
     * @return bool
     */
    public function affectsCash(): bool
    {
        return $this->payment_method === self::PAYMENT_METHOD_CASH;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashDrawerSession::class, 'session_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
