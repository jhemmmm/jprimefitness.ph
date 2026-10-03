<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\SyncsToOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Project-local Role model. Adds `SyncsToOutbox` so role definitions
 * (name, guard_name, uuid, color) replicate between live and local through
 * the standard sync pipeline.
 */
class Role extends SpatieRole
{
    use SyncsToOutbox;

    /** Badge palette keys; each has a `.m-badge--{color}` style in resources/sass/panel.scss. */
    public const COLORS = ['slate', 'sky', 'indigo', 'purple', 'pink', 'rose', 'orange', 'amber', 'green', 'teal'];

    /** Seeded roles (RoleSeeder::MATRIX). Code refers to them by name, so they can't be renamed or removed. */
    public const BUILT_IN = ['super admin', 'admin', 'manager', 'cashier', 'staff', 'coach', 'member'];

    /** Never editable under Settings: super admin always has the full admin set; members must never gain panel permissions. */
    public const LOCKED_ROLES = ['super admin', 'member'];

    /**
     * Roles listed on (and assignable from) the Employees page / global search.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeEmployee(Builder $query): Builder
    {
        return $query->whereNotIn('name', User::NON_EMPLOYEE_ROLES);
    }

    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeEditable(Builder $query): Builder
    {
        return $query->whereNotIn('name', self::LOCKED_ROLES);
    }

    /**
     * Employee roles a user may hand out: the built-ins as always, and custom roles only when
     * they grant nothing the user lacks, so an employee manager can't escalate anyone past themselves.
     *
     * @return Collection<int, Role>
     */
    public static function assignableBy(User $user): Collection
    {
        $held = $user->getAllPermissions()->pluck('name');

        return static::query()->employee()->with('permissions:id,name')->orderBy('name')->get()
            ->filter(fn (self $role) => $role->isBuiltIn() || $role->permissions->pluck('name')->diff($held)->isEmpty())
            ->values();
    }

    public function isEditable(): bool
    {
        return ! in_array($this->name, self::LOCKED_ROLES, true);
    }

    public function isBuiltIn(): bool
    {
        return in_array($this->name, self::BUILT_IN, true);
    }
}
