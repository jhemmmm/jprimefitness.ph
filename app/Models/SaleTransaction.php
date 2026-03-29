<?php

namespace App\Models;

use Database\Factories\SaleTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleTransaction extends Model
{
    /** @use HasFactory<SaleTransactionFactory> */
    use HasFactory;

    public const TYPE_INVENTORY = 'inventory';

    public const TYPE_MEMBERSHIP = 'membership';

    public const TYPE_PT_PACKAGE = 'pt_package';

    public const TYPE_WALK_IN = 'walk_in';

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_ONLINE_PAYMENT = 'online_payment';

    public const PAYMENT_METHOD_GCASH = 'gcash';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $fillable = [
        'branch_id',
        'member_id',
        'type',
        'total',
        'payment_method',
        'processed_by',
        'sold_at',
        'customer_name',
        'item_name',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'sold_at' => 'datetime',
            'details' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
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
