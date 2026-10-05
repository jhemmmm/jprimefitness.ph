<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSubscription extends Model
{
    use SyncsToOutbox;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAUSED = 'paused';

    public const PENDING_PAYMENT_ON_SITE = 'on_site';

    public const PENDING_PAYMENT_ONLINE = 'online';

    protected $fillable = [
        'user_id',
        'rate_plan_id',
        'sold_price',
        'start_date',
        'end_date',
        'status',
        'pending_payment_method',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'qr_payload',
        'qr_generated_at',
        'qr_emailed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sold_price' => 'decimal:2',
        'expiration_notification_sent_for_date' => 'date',
        'cancelled_at' => 'datetime',
        'qr_generated_at' => 'datetime',
        'qr_emailed_at' => 'datetime',
    ];

    /**
     * Store cancellation timestamps in the application's database timezone.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function cancelledAt(): Attribute
    {
        return Attribute::make(
            set: fn (DateTimeInterface|string|null $value): ?string => $value === null
                ? null
                : Carbon::parse($value, config('app.timezone'))
                    ->setTimezone(config('app.timezone'))
                    ->format($this->getDateFormat()),
        );
    }

    /**
     * The membership a member is on right now: the latest active/paused plan that
     * has started wins over an upcoming one (e.g. a pre-paid renewal), which is
     * only "current" when nothing is running. $subscriptions: ordered by start_date desc.
     *
     * @param  Collection<int, self>  $subscriptions
     */
    public static function currentOf(Collection $subscriptions): ?self
    {
        $open = $subscriptions->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAUSED]);

        return $open->first(fn (self $s) => $s->start_date === null || $s->start_date->lte(today())) ?? $open->last();
    }

    /**
     * A paid follow-up plan for the same member that starts after this one ends,
     * i.e. the member has already renewed and needs no expiry reminder.
     * Reads member.memberSubscriptions, so eager-load it when looping.
     */
    public function hasRenewal(): bool
    {
        return $this->end_date !== null && ($this->member?->memberSubscriptions ?? collect())
            ->contains(fn (self $s) => $s->status === self::STATUS_ACTIVE && $s->start_date?->gt($this->end_date));
    }

    /** Flipped by panel:expire-memberships, or simply past its end date. */
    public function isExpired(CarbonInterface $at): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->end_date !== null && $this->end_date->copy()->endOfDay()->lessThan($at));
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

}
