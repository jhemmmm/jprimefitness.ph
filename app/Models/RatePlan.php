<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Builder;
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

    /** Plans a member can be put on: active, not removed (removal nulls the price), and not the walk-in day pass. */
    public function scopeMembership(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('price')->where('is_walk_in_only', false);
    }

    public function memberSubscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
    }
}
