<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatePlan extends Model
{
    protected $fillable = [
        'name',
        'duration_days',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_rate_prices')
            ->withPivot(['price', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function memberSubscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
    }
}
