<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\SaleTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleTransaction extends Model
{
    /** @use HasFactory<SaleTransactionFactory> */
    use HasFactory, SyncsToOutbox;

    public const TYPE_INVENTORY = 'inventory';

    public const TYPE_MEMBERSHIP = 'membership';

    public const TYPE_PT_PACKAGE = 'pt_package';

    public const TYPE_WALK_IN = 'walk_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_ONLINE_PAYMENT = 'online_payment';

    public const PAYMENT_METHOD_GCASH = 'gcash';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $fillable = [
        'member_id',
        'type',
        'status',
        'total',
        'payment_method',
        'processed_by',
        'sold_at',
        'customer_name',
        'item_name',
        'details',
        'void_reason',
        'voided_by',
        'voided_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_COMPLETED,
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'sold_at' => 'datetime',
            'details' => 'array',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Get the member attached to the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /**
     * Get the panel user who processed the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the panel user who voided the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Scope the query to completed transactions.
     *
     * @return void
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Determine whether the transaction has been voided.
     *
     * @return bool
     */
    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * @return array<int, string>
     */
    public static function supportedPaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_CASH,
            self::PAYMENT_METHOD_GCASH,
            self::PAYMENT_METHOD_CARD,
            self::PAYMENT_METHOD_BANK_TRANSFER,
            self::PAYMENT_METHOD_ONLINE_PAYMENT,
        ];
    }

    public function receiptNumber(): string
    {
        $date = $this->sold_at?->format('Ymd') ?? now()->format('Ymd');

        return 'SLS-'.$date.'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function amountReceived(): float
    {
        return round((float) data_get($this->details, 'payment.amount_received', $this->total), 2);
    }

    public function changeAmount(): float
    {
        return round((float) data_get($this->details, 'payment.change_amount', 0), 2);
    }

    public function paymentReference(): ?string
    {
        $reference = data_get($this->details, 'payment.reference');

        if ($reference === null || $reference === '') {
            return null;
        }

        return (string) $reference;
    }

    public static function paymentMethodLabel(?string $paymentMethod): string
    {
        return match ($paymentMethod) {
            self::PAYMENT_METHOD_CASH => 'Cash',
            self::PAYMENT_METHOD_GCASH => 'GCash',
            self::PAYMENT_METHOD_CARD => 'Card',
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_METHOD_ONLINE_PAYMENT => 'Online Payment',
            default => str((string) $paymentMethod)->replace('_', ' ')->title()->toString(),
        };
    }
}
