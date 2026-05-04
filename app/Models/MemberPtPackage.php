<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberPtPackage extends Model
{
    use SyncsToOutbox;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CONSUMED = 'consumed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'pt_product_id',
        'sold_price',
        'coach_id',
        'total_sessions',
        'remaining_sessions',
        'assigned_at',
        'expires_at',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'assigned_at' => 'date',
        'expires_at' => 'date',
        'sold_price' => 'decimal:2',
    ];

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

            $package->save();

            return $usage->fresh(['coach', 'recordedBy']);
        });
    }

}
