<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            [
                'role' => 'manager',
                'email' => 'manager@example.com',
                'name' => 'Manager Mia',
                'daily_rate' => 850,
                'pay_frequency' => 'semi_monthly',
            ],
            [
                'role' => 'admin',
                'email' => 'admin@example.com',
                'name' => 'Admin Ava',
                'daily_rate' => 900,
                'pay_frequency' => 'semi_monthly',
            ],
            [
                'role' => 'staff',
                'email' => 'staff1@example.com',
                'name' => 'Staff Sol',
                'daily_rate' => 575,
                'pay_frequency' => 'semi_monthly',
            ],
            [
                'role' => 'staff',
                'email' => 'staff2@example.com',
                'name' => 'Staff Ina',
                'daily_rate' => 575,
                'pay_frequency' => 'semi_monthly',
            ],
            [
                'role' => 'coach',
                'email' => 'coach@example.com',
                'name' => 'Coach Cole',
                'daily_rate' => 700,
                'pay_frequency' => 'semi_monthly',
            ],
            [
                'role' => 'employee',
                'email' => 'employee@example.com',
                'name' => 'Employee Eli',
                'daily_rate' => 550,
                'pay_frequency' => 'semi_monthly',
            ],
        ];

        foreach ($employees as $spec) {
            $user = User::query()->updateOrCreate(
                ['email' => $spec['email']],
                [
                    'name' => $spec['name'],
                    'password' => Hash::make('password'),
                    'phone' => fake()->phoneNumber(),
                    'status' => User::STATUS_ACTIVE,
                ]
            );

            $user->syncRoles([$spec['role']]);

            $profile = EmployeeProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'hikvision_employee_no' => str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
                    'biometric_status' => EmployeeProfile::STATUS_NOT_ENROLLED,
                ],
            );

            $profile->update([
                'daily_rate' => $spec['daily_rate'],
                'pay_frequency' => $spec['pay_frequency'],
            ]);
        }
    }
}
