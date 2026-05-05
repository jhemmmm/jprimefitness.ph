<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

class ProductionSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the production defaults.
     *
     * @return void
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->call([
            RoleSeeder::class,
            BusinessProfileSeeder::class,
            RatePlansSeeder::class,
            PTProductSeeder::class,
        ]);

        $this->seedSuperAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create or update the configured super admin account.
     *
     * @return void
     */
    private function seedSuperAdmin(): void
    {
        $name = (string) config('production.super_admin.name');
        $email = (string) config('production.super_admin.email');
        $password = (string) config('production.super_admin.password');

        if ($email === '') {
            throw new InvalidArgumentException('Set PRODUCTION_SUPER_ADMIN_EMAIL before running ProductionSeeder.');
        }

        if ($password === '') {
            throw new InvalidArgumentException('Set PRODUCTION_SUPER_ADMIN_PASSWORD before running ProductionSeeder.');
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => User::STATUS_ACTIVE,
            ],
        );

        $user->syncRoles(['super admin']);
    }
}
