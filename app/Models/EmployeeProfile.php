<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory, SyncsToOutbox;

    public const STATUS_NOT_ENROLLED = 'not_enrolled';

    public const STATUS_ENROLLING = 'enrolling';

    public const STATUS_ENROLLED = 'enrolled';

    public const STATUS_FAILED = 'failed';

    /**
     * Monthly statutory minimums (PHP). New employees and blank inputs default to these.
     *
     * @var array<string, int>
     */
    public const LEGAL_MINIMUM_CONTRIBUTIONS = [
        'sss_employee_share' => 250,
        'sss_employer_share' => 500,
        'philhealth_employee_share' => 250,
        'philhealth_employer_share' => 250,
        'pagibig_employee_share' => 100,
        'pagibig_employer_share' => 100,
    ];

    protected $fillable = [
        'user_id',
        'hikvision_employee_no',
        'biometric_status',
        'biometric_fingerprint_id',
        'biometric_enrolled_at',
        'biometric_last_error',
        'daily_rate',
        'pay_frequency',
        'pt_commission_rate',
        'sss_covered',
        'sss_employee_share',
        'sss_employer_share',
        'philhealth_covered',
        'philhealth_employee_share',
        'philhealth_employer_share',
        'pagibig_covered',
        'pagibig_employee_share',
        'pagibig_employer_share',
    ];

    protected function casts(): array
    {
        return [
            'biometric_enrolled_at' => 'datetime',
            'daily_rate' => 'decimal:2',
            'pay_frequency' => 'string',
            'pt_commission_rate' => 'decimal:2',
            'sss_covered' => 'boolean',
            'sss_employee_share' => 'decimal:2',
            'sss_employer_share' => 'decimal:2',
            'philhealth_covered' => 'boolean',
            'philhealth_employee_share' => 'decimal:2',
            'philhealth_employer_share' => 'decimal:2',
            'pagibig_covered' => 'boolean',
            'pagibig_employee_share' => 'decimal:2',
            'pagibig_employer_share' => 'decimal:2',
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

    public function scheduleShifts(): HasMany
    {
        return $this->hasMany(EmployeeScheduleShift::class)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }
}
