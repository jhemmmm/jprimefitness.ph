<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use Illuminate\Database\Seeder;

class BusinessProfileSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = array_merge(BusinessProfile::defaultAttributes(), [
            'name' => 'JPRIME Fitness',
            'city' => 'Naga City',
            'province' => 'Camarines Sur',
            'address' => 'Magarao Highway, Naga City, Camarines Sur',
            'amenities' => [
                'Free weights',
                'Functional training zone',
                'Personal training',
                'Cardio machines',
                'Locker area',
                'Showers',
                'Wi-Fi',
            ],
            'operating_hours' => [
                ['day' => 'Monday', 'hours' => '6:00 AM - 10:00 PM'],
                ['day' => 'Tuesday', 'hours' => '6:00 AM - 10:00 PM'],
                ['day' => 'Wednesday', 'hours' => '6:00 AM - 10:00 PM'],
                ['day' => 'Thursday', 'hours' => '6:00 AM - 10:00 PM'],
                ['day' => 'Friday', 'hours' => '6:00 AM - 10:00 PM'],
                ['day' => 'Saturday', 'hours' => '7:00 AM - 9:00 PM'],
                ['day' => 'Sunday', 'hours' => '8:00 AM - 8:00 PM'],
            ],
        ]);

        $profile = BusinessProfile::query()->first();

        if ($profile !== null) {
            $profile->update($attributes);

            return;
        }

        BusinessProfile::query()->create($attributes);
    }
}
