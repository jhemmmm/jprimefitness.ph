<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    use SyncsToOutbox;

    public const DISCOUNT_STUDENT = 'student';

    public const DISCOUNT_SENIOR = 'senior';

    public const DISCOUNT_PERCENT = 20;

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
        'discount_type',
    ];

    public function hasDiscount(): bool
    {
        return $this->discount_type === self::DISCOUNT_STUDENT
            || $this->discount_type === self::DISCOUNT_SENIOR;
    }

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
