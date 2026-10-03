<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RatePlansSeeder::class,
            PTProductSeeder::class,
            InventoryCategorySeeder::class,
            InventoryItemSeeder::class,
            RoleSeeder::class,
            BusinessProfileSeeder::class,
            UserSeeder::class,
            MemberSeeder::class,
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
