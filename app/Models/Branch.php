<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    const COUNTRY_PHILIPPINES = 'PH';

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    const STATUS_COMING_SOON = 'coming_soon';

    const PAYROLL_FREQUENCY_MONTHLY = 'monthly';

    const PAYROLL_FREQUENCY_SEMI_MONTHLY = 'semi_monthly';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'country_code',
        'city',
        'province',
        'address',
        'phone',
        'email',
        'messenger_url',
        'facebook_url',
        'whatsapp_url',
        'map_url',
        'photos',
        'opening_time',
        'closing_time',
        'amenities',
        'operating_hours',
        'timezone',
    ];

    protected $casts = [
        'amenities' => 'array',
        'operating_hours' => 'array',
        'photos' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Branch $branch): void {
            $branch->slug = static::resolveUniqueSlug($branch->slug ?: $branch->name);
        });

        static::updating(function (Branch $branch): void {
            if ($branch->isDirty('name')) {
                $branch->slug = static::resolveUniqueSlug($branch->name, $branch);
            }
        });
    }

    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(RatePlan::class, 'branch_rate_prices')
            ->withPivot(['price', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function ptProducts(): BelongsToMany
    {
        return $this->belongsToMany(PTProduct::class, 'branch_pt_prices', 'branch_id', 'pt_product_id')
            ->withPivot(['price', 'coach_commission_rate', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function cashLedgerEntries(): HasMany
    {
        return $this->hasMany(BranchCashLedgerEntry::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class)->orderBy('name');
    }

    protected static function resolveUniqueSlug(string $value, ?self $ignore = null): string
    {
        $baseSlug = Str::slug($value) ?: 'branch';
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
