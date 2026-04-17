<?php

namespace App\Models;

use Database\Factories\HikvisionEventLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HikvisionEventLog extends Model
{
    /** @use HasFactory<HikvisionEventLogFactory> */
    use HasFactory;

    protected $fillable = [
        'device_serial',
        'event_serial_no',
        'event_type',
        'employee_no',
        'attendance_id',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
