<?php

namespace Database\Seeders;

use App\Models\MemberProfile;
use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $plans = RatePlan::query()->get()->keyBy('name');

        $members = [
            [
                'user' => [
                    'name' => 'Maria Santos',
                    'email' => 'maria.santos@example.com',
                    'phone' => '+63 912 000 0001',
                ],
                'profile' => [
                    'date_of_birth' => '1995-06-15',
                    'gender' => 'female',
                    'emergency_contact_name' => 'Pedro Santos',
                    'emergency_contact_phone' => '+63 912 100 0001',
                ],
                'rate_plan' => 'Monthly',
                'start_date' => now()->startOfMonth()->toDateString(),
            ],
            [
                'user' => [
                    'name' => 'Jose Reyes',
                    'email' => 'jose.reyes@example.com',
                    'phone' => '+63 912 000 0002',
                ],
                'profile' => [
                    'date_of_birth' => '1990-03-22',
                    'gender' => 'male',
                    'emergency_contact_name' => 'Ana Reyes',
                    'emergency_contact_phone' => '+63 912 100 0002',
                ],
                'rate_plan' => '3 Months',
                'start_date' => now()->subDays(10)->toDateString(),
            ],
            [
                'user' => [
                    'name' => 'Liza Cruz',
                    'email' => 'liza.cruz@example.com',
                    'phone' => '+63 912 000 0003',
                ],
                'profile' => [
                    'date_of_birth' => '2000-11-08',
                    'gender' => 'female',
                ],
                'rate_plan' => 'Daily Pass',
                'start_date' => now()->toDateString(),
            ],
            [
                'user' => [
                    'name' => 'Anna Garcia',
                    'email' => 'anna.garcia@example.com',
                    'phone' => '+63 912 000 0004',
                ],
                'profile' => [
                    'date_of_birth' => '1997-02-14',
                    'gender' => 'female',
                ],
                'rate_plan' => 'Monthly',
                'start_date' => now()->startOfMonth()->toDateString(),
            ],
            [
                'user' => [
                    'name' => 'Mark Dela Torre',
                    'email' => 'mark.delatorre@example.com',
                    'phone' => '+63 912 000 0005',
                ],
                'profile' => [
                    'date_of_birth' => '1993-09-17',
                    'gender' => 'male',
                    'emergency_contact_name' => 'Luz Dela Torre',
                    'emergency_contact_phone' => '+63 912 100 0005',
                ],
                'rate_plan' => '6 Months',
                'start_date' => now()->subDays(12)->toDateString(),
            ],
            [
                'user' => [
                    'name' => 'Bryan Mendoza',
                    'email' => 'bryan.mendoza@example.com',
                    'phone' => '+63 912 000 0006',
                ],
                'profile' => [
                    'date_of_birth' => '1985-12-25',
                    'gender' => 'male',
                    'notes' => 'Has knee injury - avoid heavy leg press.',
                ],
                'rate_plan' => 'Annual',
                'start_date' => now()->startOfMonth()->toDateString(),
            ],
            [
                'user' => [
                    'name' => 'Sophia Aquino',
                    'email' => 'sophia.aquino@example.com',
                    'phone' => '+63 912 000 0007',
                ],
                'profile' => [
                    'date_of_birth' => '2001-08-19',
                    'gender' => 'female',
                    'emergency_contact_name' => 'Mario Aquino',
                    'emergency_contact_phone' => '+63 912 100 0007',
                ],
                'rate_plan' => 'Daily Pass',
                'start_date' => now()->toDateString(),
            ],
        ];

        foreach ($members as $entry) {
            $ratePlan = $plans->get($entry['rate_plan']);

            if (! $ratePlan) {
                continue;
            }

            $user = User::query()->updateOrCreate(
                ['email' => $entry['user']['email']],
                array_merge($entry['user'], [
                    'status' => User::STATUS_ACTIVE,
                    'password' => Hash::make('password'),
                ])
            );

            $user->syncRoles(['member']);

            MemberProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $entry['profile']
            );

            $startDate = Carbon::parse($entry['start_date']);
            $endDate = $ratePlan->duration_days > 1
                ? $startDate->copy()->addDays($ratePlan->duration_days - 1)
                : null;
            $soldPrice = (float) ($ratePlan->price ?? 0);

            $user->memberSubscriptions()->delete();
            $user->memberSubscriptions()->create([
                'rate_plan_id' => $ratePlan->id,
                'sold_price' => $soldPrice,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'status' => MemberSubscription::STATUS_ACTIVE,
            ]);
        }
    }
}
