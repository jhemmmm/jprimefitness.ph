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

    public const DISCOUNT_PWD = 'pwd';

    /** Accepted discount types and their display labels (also exposed to Vue as window.JPrime.discountLabels). */
    public const DISCOUNT_LABELS = [
        self::DISCOUNT_STUDENT => 'Student',
        self::DISCOUNT_SENIOR => 'Senior citizen',
        self::DISCOUNT_PWD => 'PWD',
    ];

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
        return in_array($this->discount_type, self::discountTypes(), true);
    }

    /**
     * @return list<string>
     */
    public static function discountTypes(): array
    {
        return array_keys(self::DISCOUNT_LABELS);
    }

    /**
     * Apply the student/senior/PWD discount to a base price.
     */
    public static function discountedPrice(float $basePrice): float
    {
        return round($basePrice * (100 - self::DISCOUNT_PERCENT) / 100, 2);
    }

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
