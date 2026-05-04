<?php

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Database\Factories\BusinessProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use HasFactory, SyncsToOutbox;

    public const COUNTRY_PHILIPPINES = 'PH';

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
            'closing_time' => '22:00',
            'amenities' => [],
            'operating_hours' => [],
            'timezone' => 'Asia/Manila',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], static::defaultAttributes());
    }

    /**
     * @return array{id:int, name:string, city:?string, province:?string, country_code:?string, pay_overwork_hours:bool, payroll_withholding_tax_enabled:bool, payroll_government_contributions_enabled:bool}
     */
    public function panelShellPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'province' => $this->province,
            'country_code' => $this->country_code,
            'pay_overwork_hours' => (bool) $this->pay_overwork_hours,
            'payroll_withholding_tax_enabled' => (bool) $this->payroll_withholding_tax_enabled,
            'payroll_government_contributions_enabled' => (bool) $this->payroll_government_contributions_enabled,
        ];
    }
}
