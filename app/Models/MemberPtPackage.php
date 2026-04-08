<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberPtPackage extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_CANCELLED = 'cancelled';

    public const COMMISSION_STATUS_UNASSIGNED = 'unassigned';

    public const COMMISSION_STATUS_PENDING = 'pending';

    public const COMMISSION_STATUS_EARNED = 'earned';

    public const COMMISSION_STATUS_PAID = 'paid';

    protected $fillable = [
        'user_id',
        'pt_product_id',
        'sold_price',
        'coach_commission_rate',
        'coach_commission_amount',
        'coach_id',
        'total_sessions',
        'remaining_sessions',
        'assigned_at',
        'expires_at',
        'status',
        'coach_commission_status',
        'coach_commission_earned_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'assigned_at' => 'date',
        'expires_at' => 'date',
        'sold_price' => 'decimal:2',
        'coach_commission_rate' => 'decimal:2',
        'coach_commission_amount' => 'decimal:2',
        'coach_commission_earned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $package): void {
            if ($package->coach_commission_status === null || $package->coach_commission_status === '') {
                $package->coach_commission_status = static::defaultCommissionStatus($package->coach_id);
            }

            if (($package->coach_commission_amount === null || (float) $package->coach_commission_amount === 0.0)
                && ((float) $package->sold_price > 0 || (float) $package->coach_commission_rate > 0)
            ) {
                $package->coach_commission_amount = static::calculateCommissionAmount(
                    (float) $package->sold_price,
                    (float) $package->coach_commission_rate
                );
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ptProduct(): BelongsTo
    {
        return $this->belongsTo(PTProduct::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commissionPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'commission_payroll_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MemberPtSessionUsage::class)->orderByDesc('used_at');
    }

    public function consumeSessions(
        int $sessionsUsed,
        string $usedAt,
        ?int $recordedBy = null,
        ?int $coachId = null,
        ?string $confirmedBy = null,
        ?string $notes = null
    ): MemberPtSessionUsage {
        return DB::transaction(function () use ($sessionsUsed, $usedAt, $recordedBy, $coachId, $confirmedBy, $notes) {
            $package = static::query()->lockForUpdate()->findOrFail($this->id);
            $resolvedCoachId = $coachId ?? $package->coach_id;

            if ($package->status !== self::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'member_pt_package_id' => ['Only active PT packages can be used.'],
                ]);
            }

            if ($sessionsUsed < 1) {
                throw ValidationException::withMessages([
                    'sessions_used' => ['Sessions used must be at least 1.'],
                ]);
            }

            if ($sessionsUsed > $package->remaining_sessions) {
                throw ValidationException::withMessages([
                    'sessions_used' => ['Sessions used may not exceed the remaining session balance.'],
                ]);
            }

            if ($package->coach_id === null && $resolvedCoachId !== null) {
                $package->coach_id = $resolvedCoachId;
            }

            if ($package->coach_id !== null && $package->coach_commission_status === self::COMMISSION_STATUS_UNASSIGNED) {
                $package->coach_commission_status = self::COMMISSION_STATUS_PENDING;
                $package->coach_commission_earned_at = null;
            }

            $usage = $package->usages()->create([
                'recorded_by' => $recordedBy,
                'coach_id' => $resolvedCoachId,
                'sessions_used' => $sessionsUsed,
                'used_at' => $usedAt,
                'confirmed_by' => $confirmedBy,
                'notes' => $notes,
            ]);

            $package->remaining_sessions -= $sessionsUsed;
            $package->status = $package->remaining_sessions === 0
                ? self::STATUS_CONSUMED
                : self::STATUS_ACTIVE;

            if ($package->remaining_sessions === 0 && $package->coach_id && $package->coach_commission_status === self::COMMISSION_STATUS_PENDING) {
                $package->coach_commission_status = self::COMMISSION_STATUS_EARNED;
                $package->coach_commission_earned_at = $usedAt;
            }

            $package->save();

            return $usage->fresh(['coach', 'recordedBy']);
        });
    }

    public static function defaultCommissionStatus(?int $coachId): string
    {
        return $coachId
            ? self::COMMISSION_STATUS_PENDING
            : self::COMMISSION_STATUS_UNASSIGNED;
    }

    public static function calculateCommissionAmount(float $soldPrice, float $commissionRate): float
    {
        return round(max(0, $soldPrice) * max(0, $commissionRate) / 100, 2);
    }
}
