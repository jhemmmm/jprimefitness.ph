<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\BusinessProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use HasFactory, SyncsToOutbox;

    public const COUNTRY_PHILIPPINES = 'PH';

    public const OPERATING_DAYS = [
        ['key' => 'monday', 'day' => 'Monday'],
        ['key' => 'tuesday', 'day' => 'Tuesday'],
        ['key' => 'wednesday', 'day' => 'Wednesday'],
        ['key' => 'thursday', 'day' => 'Thursday'],
        ['key' => 'friday', 'day' => 'Friday'],
        ['key' => 'saturday', 'day' => 'Saturday'],
        ['key' => 'sunday', 'day' => 'Sunday'],
    ];

    protected $fillable = [
        'name',
        'country_code',
        'pay_overwork_hours',
        'payroll_withholding_tax_enabled',
        'payroll_government_contributions_enabled',
        'city',
        'province',
        'address',
        'opening_time',
        'closing_time',
        'amenities',
        'operating_hours',
        'timezone',
    ];

    protected $casts = [
        'pay_overwork_hours' => 'boolean',
        'payroll_withholding_tax_enabled' => 'boolean',
        'payroll_government_contributions_enabled' => 'boolean',
        'amenities' => 'array',
        'operating_hours' => 'array',
    ];

    /**
     * Get the default business profile attributes.
     *
     * @return array<string, mixed>
     */
    public static function defaultAttributes(): array
    {
        return [
            'name' => 'JPrime Fitness',
            'country_code' => self::COUNTRY_PHILIPPINES,
            'pay_overwork_hours' => false,
            'payroll_withholding_tax_enabled' => false,
            'payroll_government_contributions_enabled' => false,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
            'address' => null,
            'opening_time' => '06:00',
            'closing_time' => '23:00',
            'amenities' => [],
            'operating_hours' => self::defaultOperatingHours(),
            'timezone' => 'Asia/Manila',
        ];
    }

    /**
     * Get the default weekly operating hours.
     *
     * @return array<int, array{key:string, day:string, opening_time:string, closing_time:string}>
     */
    public static function defaultOperatingHours(): array
    {
        return collect(self::OPERATING_DAYS)
            ->map(fn (array $day): array => [
                ...$day,
                'opening_time' => in_array($day['key'], ['saturday', 'sunday'], true) ? '08:00' : '06:00',
                'closing_time' => '23:00',
            ])
            ->all();
    }

    /**
     * Get the allowed operating day names.
     *
     * @return array<int, string>
     */
    public static function operatingDayNames(): array
    {
        return array_column(self::OPERATING_DAYS, 'day');
    }

    /**
     * Get adjacent days grouped by matching operating hours.
     *
     * @return array<int, array{days:string, opening_time:string, closing_time:string}>
     */
    public function operatingHourGroups(): array
    {
        $groups = [];

        foreach ($this->operating_hours ?: self::defaultOperatingHours() as $entry) {
            if (empty($entry['day']) || empty($entry['opening_time']) || empty($entry['closing_time'])) {
                continue;
            }

            $hoursKey = $entry['opening_time'].'-'.$entry['closing_time'];
            $lastGroupIndex = array_key_last($groups);

            if ($lastGroupIndex !== null && $groups[$lastGroupIndex]['hours_key'] === $hoursKey) {
                $groups[$lastGroupIndex]['days'][] = $entry['day'];

                continue;
            }

            $groups[] = [
                'days' => [$entry['day']],
                'hours_key' => $hoursKey,
                'opening_time' => $entry['opening_time'],
                'closing_time' => $entry['closing_time'],
            ];
        }

        return collect($groups)
            ->map(fn (array $group): array => [
                'days' => $this->formatOperatingDayRange($group['days']),
                'opening_time' => $group['opening_time'],
                'closing_time' => $group['closing_time'],
            ])
            ->all();
    }

    /**
     * Get a display label for all operating hours.
     *
     * @return string|null
     */
    public function formattedOperatingHours(): ?string
    {
        $label = collect($this->operatingHourGroups())
            ->map(fn (array $group): string => $group['days'].' '
                .Carbon::parse($group['opening_time'])->format('g:i A')
                .' - '
                .Carbon::parse($group['closing_time'])->format('g:i A'))
            ->join(', ');

        return $label !== '' ? $label : null;
    }

    /**
     * Get the current business profile.
     *
     * @return self
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], static::defaultAttributes());
    }

    /**
     * Get the business profile payload for the panel shell.
     *
     * @return array{id:int, name:string, city:?string, province:?string, country_code:?string, operating_hours:array<int, mixed>, pay_overwork_hours:bool, payroll_withholding_tax_enabled:bool, payroll_government_contributions_enabled:bool}
     */
    public function panelShellPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'province' => $this->province,
            'country_code' => $this->country_code,
            'operating_hours' => $this->operating_hours ?: self::defaultOperatingHours(),
            'pay_overwork_hours' => (bool) $this->pay_overwork_hours,
            'payroll_withholding_tax_enabled' => (bool) $this->payroll_withholding_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $this->payroll_government_contributions_enabled,
        ];
    }

    /**
     * Format a group of adjacent operating days.
     *
     * @param  array<int, string>  $days
     * @return string
     */
    private function formatOperatingDayRange(array $days): string
    {
        if (count($days) === 1) {
            return $days[0];
        }

        return $days[0].'-'.$days[array_key_last($days)];
    }
}
