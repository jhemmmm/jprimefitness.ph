<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAUSED = 'paused';

    public const COMMISSION_STATUS_UNASSIGNED = 'unassigned';

    public const COMMISSION_STATUS_EARNED = 'earned';

    public const COMMISSION_STATUS_PAID = 'paid';

    protected $fillable = [
        'user_id',
        'rate_plan_id',
        'sold_price',
        'manager_id',
        'manager_commission_rate',
        'manager_commission_amount',
        'start_date',
        'end_date',
        'status',
        'manager_commission_status',
        'manager_commission_earned_at',
        'commission_payroll_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sold_price' => 'decimal:2',
        'manager_commission_rate' => 'decimal:2',
        'manager_commission_amount' => 'decimal:2',
        'manager_commission_earned_at' => 'datetime',
        'expiration_notification_sent_for_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if ($subscription->manager_commission_status === null || $subscription->manager_commission_status === '') {
                $subscription->manager_commission_status = static::defaultCommissionStatus($subscription->manager_id);
            }

            if (($subscription->manager_commission_amount === null || (float) $subscription->manager_commission_amount === 0.0)
                && ((float) $subscription->sold_price > 0 || (float) $subscription->manager_commission_rate > 0)
                && $subscription->manager_id !== null
            ) {
                $subscription->manager_commission_amount = static::calculateCommissionAmount(
                    (float) $subscription->sold_price,
                    (float) $subscription->manager_commission_rate
                );
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function commissionPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'commission_payroll_id');
    }

    public static function defaultCommissionStatus(?int $managerId): string
    {
        return $managerId
            ? self::COMMISSION_STATUS_EARNED
            : self::COMMISSION_STATUS_UNASSIGNED;
    }

    public static function calculateCommissionAmount(float $soldPrice, float $commissionRate): float
    {
        return round(max(0, $soldPrice) * max(0, $commissionRate) / 100, 2);
    }

    public function effectiveManagerCommissionStatus(): string
    {
        if ($this->manager_commission_status !== null && $this->manager_commission_status !== '') {
            return $this->manager_commission_status;
        }

        return static::defaultCommissionStatus($this->manager_id);
    }

    public function hasTrackedManagerCommission(): bool
    {
        return $this->manager_id !== null
            || $this->commission_payroll_id !== null
            || $this->manager_commission_status !== null
            || $this->manager_commission_earned_at !== null
            || (float) $this->sold_price > 0
            || (float) $this->manager_commission_rate > 0
            || (float) $this->manager_commission_amount > 0;
    }

    public function hasAssignableManagerCommissionSnapshot(): bool
    {
        return (float) $this->sold_price > 0
            && (float) $this->manager_commission_rate > 0;
    }

    public function isManagerCommissionLocked(): bool
    {
        return $this->commission_payroll_id !== null
            || in_array($this->effectiveManagerCommissionStatus(), [
                self::COMMISSION_STATUS_EARNED,
                self::COMMISSION_STATUS_PAID,
            ], true);
    }

    public function managerCommissionLockReason(): ?string
    {
        if (! $this->isManagerCommissionLocked()) {
            return null;
        }

        if ($this->commission_payroll_id !== null && $this->effectiveManagerCommissionStatus() === self::COMMISSION_STATUS_PAID) {
            return 'This membership sale commission has already been paid through payroll.';
        }

        if ($this->commission_payroll_id !== null) {
            return 'This membership sale commission is already linked to a payroll record.';
        }

        if ($this->effectiveManagerCommissionStatus() === self::COMMISSION_STATUS_PAID) {
            return 'This membership sale commission has already been paid.';
        }

        return 'This membership sale commission is already earned and pending payroll.';
    }

    public function canAssignManagerCommission(): bool
    {
        return ! $this->isManagerCommissionLocked()
            && $this->manager_id === null
            && $this->effectiveManagerCommissionStatus() === self::COMMISSION_STATUS_UNASSIGNED
            && $this->hasAssignableManagerCommissionSnapshot();
    }

    public function managerCommissionAssignmentReason(): ?string
    {
        if ($this->canAssignManagerCommission()) {
            return null;
        }

        if ($this->isManagerCommissionLocked()) {
            return $this->managerCommissionLockReason();
        }

        if ($this->manager_id !== null || $this->effectiveManagerCommissionStatus() !== self::COMMISSION_STATUS_UNASSIGNED) {
            return 'This membership sale commission is already assigned.';
        }

        if (! $this->hasAssignableManagerCommissionSnapshot()) {
            return 'This membership sale does not have an assignable commission snapshot yet.';
        }

        return 'This membership sale commission cannot be assigned.';
    }
}
