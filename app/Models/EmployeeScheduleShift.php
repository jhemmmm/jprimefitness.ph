<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\EmployeeScheduleShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeScheduleShift extends Model
{
    /** @use HasFactory<EmployeeScheduleShiftFactory> */
    use HasFactory, SyncsToOutbox;

    protected $fillable = [
        'uuid',
        'employee_profile_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_time' => 'string',
            'end_time' => 'string',
        ];
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }
}
