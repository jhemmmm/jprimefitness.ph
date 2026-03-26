<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MemberProfile;
use App\Models\RatePlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::all()->keyBy('slug');
        $daily = RatePlan::where('slug', 'daily-pass')->first();
        $monthly = RatePlan::where('slug', 'monthly')->first();

        $role = Role::firstOrCreate(['name' => 'member']);

        $members = [
            // Calabanga branch
            [
                'user' => [
                    'name' => 'Maria Santos',
                    'email' => 'maria.santos@example.com',
                    'phone' => '+63 912 000 0001',
                    'branch_id' => $branches['calabanga']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1995-06-15',
                    'gender' => 'female',
                    'emergency_contact_name' => 'Pedro Santos',
                    'emergency_contact_phone' => '+63 912 100 0001',
                ],
                'rate_plan' => $monthly,
                'start_date' => now()->startOfMonth(),
            ],
            [
                'user' => [
                    'name' => 'Jose Reyes',
                    'email' => 'jose.reyes@example.com',
                    'phone' => '+63 912 000 0002',
                    'branch_id' => $branches['calabanga']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1990-03-22',
                    'gender' => 'male',
                    'emergency_contact_name' => 'Ana Reyes',
                    'emergency_contact_phone' => '+63 912 100 0002',
                ],
                'rate_plan' => $monthly,
                'start_date' => now()->subDays(10),
            ],
            [
                'user' => [
                    'name' => 'Liza Cruz',
                    'email' => 'liza.cruz@example.com',
                    'phone' => '+63 912 000 0003',
                    'branch_id' => $branches['calabanga']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '2000-11-08',
                    'gender' => 'female',
                ],
                'rate_plan' => $daily,
                'start_date' => now(),
            ],

            // Naga branch
            [
                'user' => [
                    'name' => 'Carlo Bautista',
                    'email' => 'carlo.bautista@example.com',
                    'phone' => '+63 912 000 0004',
                    'branch_id' => $branches['naga']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1988-07-30',
                    'gender' => 'male',
                    'emergency_contact_name' => 'Rosa Bautista',
                    'emergency_contact_phone' => '+63 912 100 0004',
                ],
                'rate_plan' => $monthly,
                'start_date' => now()->subDays(5),
            ],
            [
                'user' => [
                    'name' => 'Anna Garcia',
                    'email' => 'anna.garcia@example.com',
                    'phone' => '+63 912 000 0005',
                    'branch_id' => $branches['naga']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1997-02-14',
                    'gender' => 'female',
                ],
                'rate_plan' => $monthly,
                'start_date' => now()->startOfMonth(),
            ],
            [
                'user' => [
                    'name' => 'Mark Dela Torre',
                    'email' => 'mark.delatorre@example.com',
                    'phone' => '+63 912 000 0006',
                    'branch_id' => $branches['naga']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1993-09-17',
                    'gender' => 'male',
                    'emergency_contact_name' => 'Luz Dela Torre',
                    'emergency_contact_phone' => '+63 912 100 0006',
                ],
                'rate_plan' => $daily,
                'start_date' => now(),
            ],

            // Legazpi branch
            [
                'user' => [
                    'name' => 'Rachel Villanueva',
                    'email' => 'rachel.villanueva@example.com',
                    'phone' => '+63 912 000 0007',
                    'branch_id' => $branches['legazpi']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1999-04-03',
                    'gender' => 'female',
                    'emergency_contact_name' => 'Ben Villanueva',
                    'emergency_contact_phone' => '+63 912 100 0007',
                ],
                'rate_plan' => $monthly,
                'start_date' => now()->subDays(15),
            ],
            [
                'user' => [
                    'name' => 'Bryan Mendoza',
                    'email' => 'bryan.mendoza@example.com',
                    'phone' => '+63 912 000 0008',
                    'branch_id' => $branches['legazpi']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '1985-12-25',
                    'gender' => 'male',
                    'notes' => 'Has knee injury — avoid heavy leg press.',
                ],
                'rate_plan' => $monthly,
                'start_date' => now()->startOfMonth(),
            ],
            [
                'user' => [
                    'name' => 'Sophia Aquino',
                    'email' => 'sophia.aquino@example.com',
                    'phone' => '+63 912 000 0009',
                    'branch_id' => $branches['legazpi']?->id,
                ],
                'profile' => [
                    'date_of_birth' => '2001-08-19',
                    'gender' => 'female',
                    'emergency_contact_name' => 'Mario Aquino',
                    'emergency_contact_phone' => '+63 912 100 0009',
                ],
                'rate_plan' => $daily,
                'start_date' => now(),
            ],
        ];

        foreach ($members as $entry) {
            $userData = array_diff_key($entry['user'], ['branch_id' => null]);
            $user = User::firstOrCreate(
                ['email' => $entry['user']['email']],
                array_merge($userData, [
                    'status' => User::STATUS_ACTIVE,
                    'password' => Hash::make('password'),
                ])
            );

            $user->assignRole($role);

            $branchIds = [];
            if (! empty($entry['user']['branch_id'])) {
                $branchIds[] = $entry['user']['branch_id'];
            }
            if (! empty($branchIds)) {
                $user->syncBranches($branchIds);
            }

            MemberProfile::firstOrCreate(
                ['user_id' => $user->id],
                $entry['profile']
            );

            if ($entry['rate_plan']) {
                $startDate = $entry['start_date'];
                $endDate = $entry['rate_plan']->duration_days > 1
                    ? $startDate->copy()->addDays($entry['rate_plan']->duration_days - 1)
                    : null;

                $user->ratePlans()->syncWithoutDetaching([
                    $entry['rate_plan']->id => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate?->toDateString(),
                        'status' => 'active',
                    ],
                ]);
            }
        }
    }
}
