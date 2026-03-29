<?php

namespace App\Models;

use Database\Factories\InventoryCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryCategory extends Model
{
    /** @use HasFactory<InventoryCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->slug = static::resolveUniqueSlug($category->slug ?: $category->name);
        });

        static::updating(function (self $category): void {
            if ($category->isDirty('name') && ! $category->isDirty('slug')) {
                $category->slug = static::resolveUniqueSlug($category->name, $category);
            }
        });
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class)->orderBy('name');
    }

    protected static function resolveUniqueSlug(string $value, ?self $ignore = null): string
    {
        $baseSlug = Str::slug($value) ?: 'inventory-category';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            static::query()
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
