<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\SyncsToOutbox;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LaravelAndVueJS\Traits\LaravelPermissionToVueJS;
use App\Models\Role;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'status',
    'email',
    'password',
    'phone',
    'photo_url',
    'address',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LaravelPermissionToVueJS, Notifiable, SoftDeletes, SyncsToOutbox;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    public function profile(): HasOne
    {
        return $this->hasOne(MemberProfile::class);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
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

    public function memberPtPackages(): HasMany
    {
        return $this->hasMany(MemberPtPackage::class)->orderByDesc('assigned_at');
    }

    public function attachPlan(int $ratePlanId, string $startDate, array $attributes = []): MemberSubscription
    {
        $plan = RatePlan::findOrFail($ratePlanId);
        $subscription = $this->memberSubscriptions()->create(array_merge([
            'rate_plan_id' => $plan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'start_date' => $startDate,
            'end_date' => $this->membershipEndDate($plan, $startDate),
        ], $attributes));

        return $subscription;
    }

    public function syncRatePlan(?int $ratePlanId, string $startDate, array $attributes = []): void
    {
        if (! $ratePlanId) {
            return;
        }

        $activePlan = $this->currentMembership();
        $plan = RatePlan::findOrFail($ratePlanId);
        $endDate = $this->membershipEndDate($plan, $startDate);

        if ($activePlan && (int) $activePlan->rate_plan_id === $ratePlanId) {
            $activePlan->update(array_merge([
                'start_date' => $startDate,
                'end_date' => $endDate,
            ], $attributes));

            return;
        }

        if ($activePlan) {
            $activePlan->update([
                'status' => MemberSubscription::STATUS_CANCELLED,
            ]);
        }

        $this->attachPlan($ratePlanId, $startDate, $attributes);
    }

    public function currentMembership(): ?MemberSubscription
    {
        return $this->memberSubscriptions()
            ->whereIn('status', [MemberSubscription::STATUS_ACTIVE, MemberSubscription::STATUS_PAUSED])
            ->orderByDesc('start_date')
            ->first();
    }

    public function changeMembershipPlan(int $ratePlanId, string $startDate, array $attributes = []): MemberSubscription
    {
        $currentPlan = $this->currentMembership();
        $plan = RatePlan::findOrFail($ratePlanId);
        $endDate = $this->membershipEndDate($plan, $startDate);

        if ($currentPlan && (int) $currentPlan->rate_plan_id === $ratePlanId) {
            $currentPlan->update(array_merge([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $currentPlan->status,
            ], $attributes));

            return $currentPlan->fresh();
        }

        if ($currentPlan) {
            $currentPlan->update([
                'status' => MemberSubscription::STATUS_CANCELLED,
            ]);
        }

        return $this->attachPlan($ratePlanId, $startDate, $attributes);
    }

    public function sellMembershipPlan(int $ratePlanId, string $startDate, array $attributes = []): MemberSubscription
    {
        $currentPlan = $this->currentMembership();

        if ($currentPlan) {
            $currentPlan->update([
                'status' => MemberSubscription::STATUS_CANCELLED,
            ]);
        }

        return $this->attachPlan($ratePlanId, $startDate, $attributes);
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    private function membershipEndDate(RatePlan $plan, string $startDate): ?string
    {
        if ($plan->duration_days <= 1) {
            return null;
        }

        return Carbon::parse($startDate)
            ->addDays($plan->duration_days - 1)
            ->toDateString();
    }
}
