<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    const COUNTRY_PHILIPPINES = 'PH';

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    const STATUS_COMING_SOON = 'coming_soon';

    const PAYROLL_FREQUENCY_MONTHLY = 'monthly';

    const PAYROLL_FREQUENCY_SEMI_MONTHLY = 'semi_monthly';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'country_code',
        'city',
        'province',
        'address',
        'phone',
        'email',
        'messenger_url',
        'facebook_url',
        'whatsapp_url',
        'map_url',
        'photos',
        'opening_time',
        'closing_time',
        'amenities',
        'operating_hours',
        'timezone',
        'payroll_settings',
    ];

    protected $casts = [
        'amenities' => 'array',
        'operating_hours' => 'array',
        'photos' => 'array',
        'payroll_settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Branch $branch): void {
            $branch->slug = static::resolveUniqueSlug($branch->slug ?: $branch->name);
        });

        static::updating(function (Branch $branch): void {
            if ($branch->isDirty('name')) {
                $branch->slug = static::resolveUniqueSlug($branch->name, $branch);
            }
        });
    }

    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(RatePlan::class, 'branch_rate_prices')
            ->withPivot(['price', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function ptProducts(): BelongsToMany
    {
        return $this->belongsToMany(PTProduct::class, 'branch_pt_prices', 'branch_id', 'pt_product_id')
            ->withPivot(['price', 'coach_commission_rate', 'is_active', 'effective_from', 'effective_until'])
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function cashLedgerEntries(): HasMany
    {
        return $this->hasMany(BranchCashLedgerEntry::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPayrollSettings(?string $countryCode = null): array
    {
        return [
            'pay_frequency' => self::PAYROLL_FREQUENCY_SEMI_MONTHLY,
            'income_tax_mode' => 'manual',
            'contributions' => [],
        ];
    }

    /**
     * @return array<int, array<string, float|string|null|bool>>
     */
    public static function governmentContributionTemplates(?string $countryCode = null): array
    {
        $countryCode = strtoupper((string) ($countryCode ?: self::COUNTRY_PHILIPPINES));

        if ($countryCode !== self::COUNTRY_PHILIPPINES) {
            return [];
        }

        return [
            [
                'name' => 'SSS',
                'employee_rate' => 4.5,
                'employer_rate' => 9.5,
                'employee_min_amount' => null,
                'employer_min_amount' => null,
                'salary_floor' => null,
                'salary_ceiling' => null,
                'enabled' => true,
            ],
            [
                'name' => 'PhilHealth',
                'employee_rate' => 2.5,
                'employer_rate' => 2.5,
                'employee_min_amount' => null,
                'employer_min_amount' => null,
                'salary_floor' => null,
                'salary_ceiling' => null,
                'enabled' => true,
            ],
            [
                'name' => 'PAG-IBIG',
                'employee_rate' => 2,
                'employer_rate' => 2,
                'employee_min_amount' => 50,
                'employer_min_amount' => 50,
                'salary_floor' => null,
                'salary_ceiling' => null,
                'enabled' => true,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array<string, mixed>
     */
    public static function normalizePayrollSettings(?array $settings, ?string $countryCode = null): array
    {
        $defaults = static::defaultPayrollSettings($countryCode);
        $settings = is_array($settings) ? $settings : [];
        $payFrequency = (string) ($settings['pay_frequency'] ?? $defaults['pay_frequency']);

        if (! in_array($payFrequency, [self::PAYROLL_FREQUENCY_MONTHLY, self::PAYROLL_FREQUENCY_SEMI_MONTHLY], true)) {
            $payFrequency = $defaults['pay_frequency'];
        }

        $rawContributions = $settings['contributions'] ?? $defaults['contributions'];
        $contributions = [];

        foreach ((array) $rawContributions as $contribution) {
            $name = trim((string) ($contribution['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $salaryFloor = $contribution['salary_floor'] ?? null;
            $salaryCeiling = $contribution['salary_ceiling'] ?? null;
            $employeeMinimumAmount = $contribution['employee_min_amount'] ?? null;
            $employerMinimumAmount = $contribution['employer_min_amount'] ?? null;

            $contributions[] = [
                'name' => $name,
                'employee_rate' => round(max(0, (float) ($contribution['employee_rate'] ?? 0)), 4),
                'employer_rate' => round(max(0, (float) ($contribution['employer_rate'] ?? 0)), 4),
                'employee_min_amount' => $employeeMinimumAmount === '' || $employeeMinimumAmount === null ? null : round(max(0, (float) $employeeMinimumAmount), 2),
                'employer_min_amount' => $employerMinimumAmount === '' || $employerMinimumAmount === null ? null : round(max(0, (float) $employerMinimumAmount), 2),
                'salary_floor' => $salaryFloor === '' || $salaryFloor === null ? null : round(max(0, (float) $salaryFloor), 2),
                'salary_ceiling' => $salaryCeiling === '' || $salaryCeiling === null ? null : round(max(0, (float) $salaryCeiling), 2),
                'enabled' => (bool) ($contribution['enabled'] ?? true),
            ];
        }

        return [
            'pay_frequency' => $payFrequency,
            'income_tax_mode' => 'manual',
            'contributions' => array_values($contributions),
        ];
    }

    public function isInPhilippines(): bool
    {
        return strtoupper((string) $this->country_code) === self::COUNTRY_PHILIPPINES;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedPayrollSettings(): array
    {
        return static::normalizePayrollSettings($this->payroll_settings, $this->country_code);
    }

    protected static function resolveUniqueSlug(string $value, ?self $ignore = null): string
    {
        $baseSlug = Str::slug($value) ?: 'branch';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            static::query()
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
