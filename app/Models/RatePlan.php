<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
