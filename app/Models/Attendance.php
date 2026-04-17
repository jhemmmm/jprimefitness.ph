<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use SoftDeletes;

    public const TYPE_MEMBER = 'member';

    public const TYPE_WALK_IN = 'walk_in';

    public const TYPE_EMPLOYEE = 'employee';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_HIKVISION = 'hikvision';

    protected $fillable = [
        'attendee_type',
        'user_id',
        'walk_in_id',
        'name',
        'checked_in_at',
        'checked_out_at',
        'notes',
        'recorded_by',
        'source',
        'source_device_serial',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getIsCheckedOutAttribute(): bool
    {
        return $this->checked_out_at !== null;
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
