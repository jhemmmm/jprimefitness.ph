<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use App\Models\MemberProfile;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // use Laravel's fake() helper
        // Employees: 5 users total (1 manager, 1 admin, 2 staff, 1 coach)
        $employees = [
            ['role' => 'manager', 'email' => 'manager@example.com', 'name' => 'Branch Manager'],
            ['role' => 'admin', 'email' => 'admin@example.com', 'name' => 'Administrator'],
            ['role' => 'staff', 'email' => 'staff1@example.com', 'name' => 'Staff One'],
            ['role' => 'staff', 'email' => 'staff2@example.com', 'name' => 'Staff Two'],
            ['role' => 'coach', 'email' => 'coach@example.com', 'name' => 'Coach One'],
        ];

        $branches = Branch::pluck('id')->toArray();

        foreach ($employees as $spec) {
            $user = User::firstOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make('password'),
                    'phone' => fake()->phoneNumber(),
                ]
            );

            if (! $user->hasRole($spec['role'])) {
                $user->assignRole($spec['role']);
            }

            if (! empty($branches)) {
                $branchId = fake()->randomElement($branches);
                $user->branches()->syncWithoutDetaching([$branchId]);
            }
        }

        // Members: 25 users with member profiles
        for ($i = 1; $i <= 25; $i++) {
            $user = User::create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => Hash::make('password'),
                'phone' => fake()->phoneNumber(),
            ]);

            if (! $user->hasRole('member')) {
                $user->assignRole('member');
            }

            $genderOptions = ['male', 'female', 'other'];

            MemberProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
                    'gender' => fake()->randomElement($genderOptions),
                    'emergency_contact_name' => fake()->name(),
                    'emergency_contact_phone' => fake()->phoneNumber(),
                    'notes' => null,
                ]
            );

            if (! empty($branches)) {
                $branchId = fake()->randomElement($branches);
                $user->branches()->syncWithoutDetaching([$branchId]);
            }
        }
    }
}
