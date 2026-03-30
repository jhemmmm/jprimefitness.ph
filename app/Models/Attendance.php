<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    const TYPE_MEMBER = 'member';

    const TYPE_WALK_IN = 'walk_in';

    const TYPE_EMPLOYEE = 'employee';

    protected $fillable = [
        'branch_id',
        'attendee_type',
        'user_id',
        'walk_in_id',
        'name',
        'checked_in_at',
        'checked_out_at',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function getIsCheckedOutAttribute(): bool
    {
        return $this->checked_out_at !== null;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walkIn(): BelongsTo
    {
        return $this->belongsTo(WalkIn::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
