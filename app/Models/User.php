<?php

namespace App\Models;

use Carbon\Carbon;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;
use Spatie\Permission\Models\Role;
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
    use HasFactory, HasRoles, LaravelPermissionToVueJS, Notifiable;

    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    const STATUS_SUSPENDED = 'suspended';

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

    public function memberSubscriptions(): HasMany
    {
        return $this->hasMany(MemberSubscription::class);
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

        $this->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function syncRatePlan(?int $ratePlanId, string $startDate): void
    {
        if (! $ratePlanId) {
            return;
        }

        $activePlan = $this->currentMembership();
        $plan = RatePlan::findOrFail($ratePlanId);
        $endDate = null;

        if ($plan->duration_days > 1) {
            $endDate = Carbon::parse($startDate)
                ->addDays($plan->duration_days - 1)
                ->toDateString();
        }

        if ($activePlan && (int) $activePlan->rate_plan_id === $ratePlanId) {
            $activePlan->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            return;
        }

        if ($activePlan) {
            $activePlan->update([
                'status' => MemberSubscription::STATUS_CANCELLED,
            ]);
        }

        $this->attachPlan($ratePlanId, $startDate);
    }

    public function currentMembership(): ?MemberSubscription
    {
        return $this->memberSubscriptions()
            ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PAUSED])
            ->orderByDesc('start_date')
            ->first();
    }

    public function changeMembershipPlan(int $ratePlanId, string $startDate): void
    {
        $currentPlan = $this->currentMembership();
        $plan = RatePlan::findOrFail($ratePlanId);
        $endDate = null;

        if ($plan->duration_days > 1) {
            $endDate = Carbon::parse($startDate)
                ->addDays($plan->duration_days - 1)
                ->toDateString();
        }

        if ($currentPlan && (int) $currentPlan->rate_plan_id === $ratePlanId) {
            $currentPlan->update([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $currentPlan->status,
            ]);

            return;
        }

        if ($currentPlan) {
            $currentPlan->update([
                'status' => MemberSubscription::STATUS_CANCELLED,
            ]);
        }

        $this->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    public function updateCurrentMembershipStatus(string $status): void
    {
        $currentPlan = $this->currentMembership();

        if (! $currentPlan) {
            return;
        }

        $currentPlan->update([
            'status' => $status,
        ]);
    }

    public function allowedEmployeesRoles(): array
    {
        $roles = Role::query()->pluck('id', 'name');

        if ($this->hasRole('super admin')) {
            return $roles->values()->all();
        }

        $excluded = ['super admin'];
        if (! $this->hasRole('admin')) {
            $excluded[] = 'admin';
        }

        return $roles
            ->except($excluded)
            ->values()
            ->all();
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
