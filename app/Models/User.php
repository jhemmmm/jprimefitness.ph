<?php

namespace App\Models;

use App\Models\Branch;
use Carbon\Carbon;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'status',
    'email',
    'password',
    'phone',
    'photo_url',
    'address',
    'daily_rate',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LaravelPermissionToVueJS, HasRoles, Notifiable;

    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_SUSPENDED = 'suspended';

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(RatePlan::class, 'member_subscriptions')
            ->withPivot(['start_date', 'end_date', 'status'])
            ->withTimestamps();
    }

    public function attachPlan(int $ratePlanId, string $startDate): void
    {
        $plan = RatePlan::findOrFail($ratePlanId);

        $endDate = null;

        if ($plan->duration_days > 1) {
            $endDate = Carbon::parse($startDate)
                ->addDays($plan->duration_days - 1)
                ->toDateString();
        }

        $this->ratePlans()->attach($plan->id, [
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function syncRatePlan(?int $ratePlanId, string $startDate): void
    {
        if (! $ratePlanId) {
            return;
        }

        $activePlan = $this->ratePlans()
            ->wherePivot('status', 'active')
            ->first();

        if ($activePlan && (int) $activePlan->id === $ratePlanId) {
            $this->ratePlans()->updateExistingPivot($activePlan->id, [
                'start_date' => $startDate,
            ]);

            return;
        }

        if ($activePlan) {
            $this->ratePlans()->updateExistingPivot($activePlan->id, [
                'status' => 'cancelled',
            ]);
        }

        $this->attachPlan($ratePlanId, $startDate);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'employee_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'employee_id');
    }

    public function cashAdvances(): HasMany
    {
        return $this->hasMany(CashAdvance::class, 'employee_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
