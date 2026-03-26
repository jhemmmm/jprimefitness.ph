<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RatePlansSeeder::class,
            PTProductSeeder::class,
            RoleSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            WalkInSeeder::class,
            AttendanceSeeder::class,
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'status' => User::STATUS_ACTIVE,
        ]);

        $role = Role::firstOrCreate(['name' => 'super admin']);
        $user->assignRole($role);
    }
}
