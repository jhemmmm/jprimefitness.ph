<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSubscription extends Model
{
    use SyncsToOutbox;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'user_id',
        'rate_plan_id',
        'sold_price',
        'start_date',
        'end_date',
        'status',
        'qr_payload',
        'qr_generated_at',
        'qr_emailed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'sold_price' => 'decimal:2',
        'expiration_notification_sent_for_date' => 'date',
        'qr_generated_at' => 'datetime',
        'qr_emailed_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

}
