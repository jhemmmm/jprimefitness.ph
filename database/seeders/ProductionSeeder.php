<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production-only seeder. Intentionally does NOT use WithoutModelEvents:
 * the super admin user must go through SyncsToOutbox boot hooks so its
 * uuid is generated and outbox rows are emitted. Suppressing model
 * events here causes the sync pipeline to silently drop role
 * assignments for the super admin (the user has no uuid, so the
 * role-assignment snapshot can't reference them).
 */
class ProductionSeeder extends Seeder
{
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
            InventoryCategorySeeder::class,
            InventoryItemSeeder::class,
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

        // Earlier versions of this seeder ran with WithoutModelEvents,
        // which suppressed the SyncsToOutbox creating hook and left the
        // super admin without a uuid. Backfill defensively so existing
        // production rows converge. forceFill() is required because
        // `uuid` is not in User's Fillable list; a plain update() would
        // silently drop the attribute.
        if (empty($user->uuid)) {
            $user->forceFill(['uuid' => (string) Str::uuid()])->save();
        }

        $user->syncRoles(['super admin']);
    }
}
