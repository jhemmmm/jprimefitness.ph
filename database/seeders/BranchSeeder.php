<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\PTProduct;
use App\Models\RatePlan;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        // ── Rate Plans ──────────────────────────────────────────────────────
        $dailyPass = RatePlan::firstOrCreate(['name' => 'Daily Pass'], [
            'name' => 'Daily Pass',
            'duration_days' => 1,
            'is_active' => true,
        ]);

        $monthly = RatePlan::firstOrCreate(['name' => 'Monthly'], [
            'name' => 'Monthly',
            'duration_days' => 30,
            'is_active' => true,
        ]);

        // ── PT Products ──────────────────────────────────────────────────────
        $perSession = PTProduct::firstOrCreate(['name' => 'Per Session'], [
            'name' => 'Per Session',
            'session_count' => 1,
            'category' => PTProduct::CATEGORY_SINGLE,
            'is_active' => true,
        ]);

        // ── Branches ─────────────────────────────────────────────────────────
        $branches = [
            [
                'data' => [
                    'name' => 'JPRIME Fitness Calabanga',
                    'slug' => 'calabanga',
                    'status' => 'open',
                    'city' => 'Calabanga',
                    'province' => 'Camarines Sur',
                    'address' => '123 Rizal Street, Brgy. Centro, Calabanga, Camarines Sur 4408',
                    'phone' => '+63 912 345 6789',
                    'messenger_url' => 'https://m.me/jprimefitness',
                    'map_url' => 'https://www.google.com/maps/search/?api=1&query=Calabanga+Camarines+Sur',
                    'opening_time' => '06:00:00',
                    'closing_time' => '23:00:00',
                    'timezone' => 'Asia/Manila',
                    'amenities' => ['Free Weights', 'Barbells & Plates', 'Cable Machines', 'Cardio Area', 'Treadmills', 'Shower Room', 'Locker Area', 'AC Room', 'CCTV', 'WiFi'],
                    'operating_hours' => [
                        ['day' => 'Monday',    'hours' => '6:00 AM – 11:00 PM'],
                        ['day' => 'Tuesday',   'hours' => '6:00 AM – 11:00 PM'],
                        ['day' => 'Wednesday', 'hours' => '6:00 AM – 11:00 PM'],
                        ['day' => 'Thursday',  'hours' => '6:00 AM – 11:00 PM'],
                        ['day' => 'Friday',    'hours' => '6:00 AM – 11:00 PM'],
                        ['day' => 'Saturday',  'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Sunday',    'hours' => '7:00 AM – 8:00 PM'],
                    ],
                ],
                'rates' => [
                    $dailyPass->id => ['price' => 100.00,  'is_active' => true],
                    $monthly->id => ['price' => 1600.00, 'is_active' => true],
                ],
                'pt_prices' => [
                    $perSession->id => ['price' => 500.00, 'is_active' => true],
                ],
            ],
            [
                'data' => [
                    'name' => 'JPRIME Fitness Naga',
                    'slug' => 'naga',
                    'status' => 'coming_soon',
                    'city' => 'Naga City',
                    'province' => 'Camarines Sur',
                    'address' => 'Magistrado, Naga City, Camarines Sur',
                    'messenger_url' => 'https://m.me/jprimefitness',
                    'whatsapp_url' => 'https://wa.me/639123456789',
                    'map_url' => 'https://www.google.com/maps/search/?api=1&query=Naga+City+Camarines+Sur',
                    'opening_time' => '01:00:00',
                    'closing_time' => '00:00:00',
                    'timezone' => 'Asia/Manila',
                    'amenities' => ['Free Weights', 'Cable Machines', 'Cardio Area', 'Shower Room', 'Locker', 'AC'],
                    'operating_hours' => [
                        ['day' => 'Monday',    'hours' => '1:00 AM – 12:00 AM'],
                        ['day' => 'Tuesday',   'hours' => '1:00 AM – 12:00 AM'],
                        ['day' => 'Wednesday', 'hours' => '1:00 AM – 12:00 AM'],
                        ['day' => 'Thursday',  'hours' => '1:00 AM – 12:00 AM'],
                        ['day' => 'Friday',    'hours' => '1:00 AM – 12:00 AM'],
                        ['day' => 'Saturday',  'hours' => '1:00 AM – 12:00 AM'],
                        ['day' => 'Sunday',    'hours' => '1:00 AM – 12:00 AM'],
                    ],
                ],
                'rates' => [
                    $dailyPass->id => ['price' => 120.00,  'is_active' => true],
                    $monthly->id => ['price' => 1800.00, 'is_active' => true],
                ],
                'pt_prices' => [
                    $perSession->id => ['price' => 400.00, 'is_active' => true],
                ],
            ],
            [
                'data' => [
                    'name' => 'JPRIME Fitness Legazpi',
                    'slug' => 'legazpi',
                    'status' => 'coming_soon',
                    'city' => 'Legazpi City',
                    'province' => 'Albay',
                    'address' => 'Satellite location, Legazpi City, Albay',
                    'messenger_url' => 'https://m.me/jprimefitness',
                    'whatsapp_url' => 'https://wa.me/639123456789',
                    'map_url' => 'https://www.google.com/maps/search/?api=1&query=Legazpi+City+Albay',
                    'opening_time' => '06:00:00',
                    'closing_time' => '22:00:00',
                    'timezone' => 'Asia/Manila',
                    'amenities' => ['Free Weights', 'Cable Machines', 'Cardio Area', 'Shower Room'],
                    'operating_hours' => [
                        ['day' => 'Monday',    'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Tuesday',   'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Wednesday', 'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Thursday',  'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Friday',    'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Saturday',  'hours' => '6:00 AM – 10:00 PM'],
                        ['day' => 'Sunday',    'hours' => '7:00 AM – 8:00 PM'],
                    ],
                ],
                'rates' => [
                    $dailyPass->id => ['price' => 120.00,  'is_active' => true],
                    $monthly->id => ['price' => 1700.00, 'is_active' => true],
                ],
                'pt_prices' => [
                    $perSession->id => ['price' => 250.00, 'is_active' => true],
                ],
            ],
        ];

        foreach ($branches as $entry) {
            $branch = Branch::firstOrCreate(
                ['slug' => $entry['data']['slug']],
                $entry['data']
            );

            foreach ($entry['rates'] as $ratePlanId => $pivot) {
                $branch->ratePlans()->syncWithoutDetaching([$ratePlanId => $pivot]);
            }

            foreach ($entry['pt_prices'] as $ptProductId => $pivot) {
                $branch->ptProducts()->syncWithoutDetaching([$ptProductId => $pivot]);
            }
        }
    }
}
