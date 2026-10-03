<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\EmployeeBiometricSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBiometricSession extends Model
{
    /** @use HasFactory<EmployeeBiometricSessionFactory> */
    use HasFactory, SyncsToOutbox;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CAPTURING = 'capturing';

    public const STATUS_UPLOADING = 'uploading';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'employee_profile_id',
        'status',
        'fingerprint_id',
        'started_by',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }
}
