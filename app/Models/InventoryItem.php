<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, SoftDeletes, SyncsToOutbox;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'inventory_category_id',
        'name',
        'sku',
        'unit',
        'quantity',
        'tracks_stock',
        'low_stock_threshold',
        'cost_price',
        'selling_price',
        'status',
        'notes',
        'last_restocked_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'tracks_stock' => 'boolean',
        'low_stock_threshold' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'last_restocked_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'is_low_stock',
        'is_out_of_stock',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function getIsLowStockAttribute(): bool
    {
        if (! $this->tracks_stock) {
            return false;
        }

        return (float) $this->quantity > 0
            && (float) $this->low_stock_threshold > 0
            && (float) $this->quantity <= (float) $this->low_stock_threshold;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        if (! $this->tracks_stock) {
            return false;
        }

        return (float) $this->quantity <= 0;
    }
}
