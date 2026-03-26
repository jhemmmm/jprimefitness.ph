<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    const STATUS_COMING_SOON = 'coming_soon';

    protected $fillable = [
        'name',
        'slug',
        'status',
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

    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(RatePlan::class, 'branch_rate_prices')
            ->withPivot(['price', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function ptProducts(): BelongsToMany
    {
        return $this->belongsToMany(PTProduct::class, 'branch_pt_prices', 'branch_id', 'pt_product_id')
            ->withPivot(['price', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
