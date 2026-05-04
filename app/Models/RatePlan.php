<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatePlan extends Model
{
    use SyncsToOutbox;

    protected $fillable = [
        'name',
        'duration_days',
        'price',
        'is_active',
        'is_walk_in_only',
        'description',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_walk_in_only' => 'boolean',
        'price' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function memberSubscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
    }
}
