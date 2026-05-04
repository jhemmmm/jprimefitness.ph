<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\HikvisionEventLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HikvisionEventLog extends Model
{
    /** @use HasFactory<HikvisionEventLogFactory> */
    use HasFactory, SyncsToOutbox;

    public function syncsUuid(): bool
    {
        return false;
    }

    public function syncEntityKey(): string
    {
        return $this->getAttribute('device_serial').':'.$this->getAttribute('event_serial_no');
    }

    public function syncableAttributes(): array
    {
        // The full Hikvision callback body lives in `payload` and can be
        // multiple KB per event. The receiving side only needs the
        // identity/timestamps to record an audit row; drop the blob.
        $attributes = $this->getAttributes();
        unset($attributes['payload']);

        return $attributes;
    }

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
