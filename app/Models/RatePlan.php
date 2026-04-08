<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatePlan extends Model
{
    protected $fillable = [
        'name',
        'duration_days',
        'price',
        'manager_commission_rate',
        'is_active',
        'description',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'manager_commission_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function memberSubscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
    }
}
