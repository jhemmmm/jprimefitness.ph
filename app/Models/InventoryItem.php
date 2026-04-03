<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'branch_id',
        'inventory_category_id',
        'name',
        'sku',
        'unit',
        'quantity',
        'low_stock_threshold',
        'cost_price',
        'selling_price',
        'status',
        'notes',
        'last_restocked_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'last_restocked_at' => 'datetime',
    ];

    protected $appends = [
        'is_low_stock',
        'is_out_of_stock',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function getIsLowStockAttribute(): bool
    {
        return (float) $this->quantity > 0
            && (float) $this->low_stock_threshold > 0
            && (float) $this->quantity <= (float) $this->low_stock_threshold;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return (float) $this->quantity <= 0;
    }
}
