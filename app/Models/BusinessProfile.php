<?php

namespace App\Models;

use Database\Factories\BusinessProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    /** @use HasFactory<BusinessProfileFactory> */
    use HasFactory;

    public const COUNTRY_PHILIPPINES = 'PH';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_COMING_SOON = 'coming_soon';

    protected $fillable = [
        'name',
        'country_code',
        'status',
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
        'hero_badge',
        'hero_title',
        'hero_highlight',
        'hero_description',
        'about_heading',
        'about_description',
        'membership_note',
    ];

    protected $casts = [
        'photos' => 'array',
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
            'status' => self::STATUS_OPEN,
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
            'address' => null,
            'phone' => null,
            'email' => null,
            'messenger_url' => null,
            'facebook_url' => null,
            'whatsapp_url' => null,
            'map_url' => null,
            'photos' => [],
            'opening_time' => '06:00',
            'closing_time' => '22:00',
            'amenities' => [],
            'operating_hours' => [],
            'timezone' => 'Asia/Manila',
            'hero_badge' => 'Single-location gym',
            'hero_title' => 'Train with purpose.',
            'hero_highlight' => 'One location. One standard.',
            'hero_description' => 'Clean facilities, straightforward pricing, and coaching that keeps the focus on real progress.',
            'about_heading' => 'Fitness that fits your goals.',
            'about_description' => 'JPrime Fitness is built around a simple promise: a clean, safe, and results-driven training space that stays accessible to everyday members.',
            'membership_note' => 'Membership, walk-in access, and PT pricing are managed from one central profile.',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], static::defaultAttributes());
    }

    /**
     * @return array{id:int, name:string, city:?string, province:?string, status:?string}
     */
    public function locationSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'province' => $this->province,
            'status' => $this->status,
        ];
    }

    /**
     * @return array{id:int, name:string, city:?string, province:?string, status:?string, country_code:?string}
     */
    public function panelShellPayload(): array
    {
        return [
            ...$this->locationSummary(),
            'country_code' => $this->country_code,
        ];
    }
}
