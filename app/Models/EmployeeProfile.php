<?php

namespace App\Models;

use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory;

    public const STATUS_NOT_ENROLLED = 'not_enrolled';

    public const STATUS_ENROLLING = 'enrolling';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'hikvision_employee_no',
        'biometric_status',
        'biometric_fingerprint_id',
        'biometric_enrolled_at',
        'biometric_last_error',
        'daily_rate',
        'pay_frequency',
    ];

    protected function casts(): array
    {
        return [
            'biometric_enrolled_at' => 'datetime',
            'daily_rate' => 'decimal:2',
            'pay_frequency' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function biometricSessions(): HasMany
    {
        return $this->hasMany(EmployeeBiometricSession::class);
    }
}
