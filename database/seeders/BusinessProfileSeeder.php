<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use Illuminate\Database\Seeder;

class BusinessProfileSeeder extends Seeder
{
    public function run(): void
    {
        $attributes = array_merge(BusinessProfile::defaultAttributes(), [
            'name' => 'JPrime Fitness',
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
            'operating_hours' => BusinessProfile::defaultOperatingHours(),
        ]);

        $profile = BusinessProfile::query()->first();

        if ($profile !== null) {
            $profile->update($attributes);

            return;
        }

        BusinessProfile::query()->create($attributes);
    }
}
