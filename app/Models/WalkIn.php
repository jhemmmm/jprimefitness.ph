<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalkIn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'rate_plan_id',
        'served_by',
        'name',
        'phone',
        'amount_paid',
        'payment_method',
        'visited_at',
        'notes',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'amount_paid' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by');
    }
}
