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
            'phone' => '+63 917 123 4567',
            'email' => 'hello@jprimefitness.local',
            'messenger_url' => 'https://m.me/jprimefitness',
            'facebook_url' => 'https://facebook.com/jprimefitness',
            'whatsapp_url' => 'https://wa.me/639171234567',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=JPRIME+Fitness+Naga+City',
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
            'hero_badge' => 'Single-location gym',
            'hero_title' => 'Train with focus.',
            'hero_highlight' => 'One location. One standard.',
            'hero_description' => 'Built for consistent coaching, clean equipment, and straightforward pricing in one reliable training space.',
            'about_heading' => 'A gym built around consistency.',
            'about_description' => 'JPRIME Fitness operates from one flagship location so the member experience stays consistent across coaching, maintenance, and day-to-day service.',
            'membership_note' => 'Memberships, walk-ins, and PT packages are managed centrally for this location.',
        ]);

        $profile = BusinessProfile::query()->first();

        if ($profile !== null) {
            $profile->update($attributes);

            return;
        }

        BusinessProfile::query()->create($attributes);
    }
}
