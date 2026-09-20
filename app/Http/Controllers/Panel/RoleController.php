<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemActivity;
use App\Services\SystemActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Roles & permissions section of Settings. Permissions are code-defined
 * (RoleSeeder::MATRIX) so only roles are created here; built-in roles keep their
 * names because code refers to them, but their permissions may change.
 */
class RoleController extends Controller
{
    /** Dropping these from a role you hold would lock you out of the panel or of this page. */
    private const SELF_LOCKOUT_PERMISSIONS = ['access panel', 'manage settings'];

    public function __construct(private SystemActivityService $systemActivityService) {}

    /**
     * Return the editable roles with every available permission and badge colour.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(): JsonResponse
    {
        $roles = Role::query()->editable()->with('permissions:id,name')->withCount($this->usersCount())->orderBy('name')->get();

        return response()->json([
            'roles' => $roles->map(fn (Role $role) => $this->serialize($role)),
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
            'colors' => Role::COLORS,
        ]);
    }

    /**
     * Create a role.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $role = Role::create(['name' => $data['name'], 'color' => $data['color']]);
        if ($permissions = $data['permissions'] ?? []) {
            $role->givePermissionTo($permissions);
        }

        $this->record($role, 'created');

        return response()->json($this->serialize($role), 201);
    }

    /**
     * Rename a custom role and/or change any role's colour and permissions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless($role->isEditable(), 404);

        $data = $this->validated($request, $role);

        if ($data['name'] !== $role->name && $role->isBuiltIn()) {
            throw ValidationException::withMessages(['name' => ['Built-in roles cannot be renamed.']]);
        }

        $wanted = collect($data['permissions'] ?? [])->unique();
        $current = $role->permissions->pluck('name');
        $granted = $wanted->diff($current)->values()->all();
        $revoked = $current->diff($wanted)->values()->all();

        if ($request->user()->hasRole($role) && array_intersect($revoked, self::SELF_LOCKOUT_PERMISSIONS)) {
            throw ValidationException::withMessages(['permissions' => ['You hold this role: keep "access panel" and "manage settings" or you would lock yourself out.']]);
        }

        $role->update(['name' => $data['name'], 'color' => $data['color']]);

        // per-permission grant/revoke rather than syncPermissions(): only these emit the sync events peers replay
        if ($granted) {
            $role->givePermissionTo($granted);
        }
        foreach ($revoked as $permission) {
            $role->revokePermissionTo($permission);
        }

        if ($granted || $revoked) {
            $this->record($role, 'updated', ['granted' => $granted, 'revoked' => $revoked]);
        }

        return response()->json($this->serialize($role->loadCount($this->usersCount())));
    }

    /**
     * Delete a custom role nobody holds.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Role $role): JsonResponse
    {
        abort_unless($role->isEditable(), 404);

        if ($role->isBuiltIn()) {
            throw ValidationException::withMessages(['role' => ['Built-in roles cannot be deleted.']]);
        }

        // trashed users count too: restoring one must not bring back an employee with no role
        if ($users = $role->users()->withTrashed()->count()) {
            throw ValidationException::withMessages(['role' => ["Reassign {$users} user(s) holding this role first."]]);
        }

        $this->record($role, 'deleted');
        $role->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * Role names are stored lower-case so lookups (unique check, badge colours) are case-insensitive everywhere.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $request->merge(['name' => Str::lower(trim((string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($role)],
            'color' => ['required', Rule::in(Role::COLORS)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);
    }

    /**
     * @return array<string, \Closure>
     */
    private function usersCount(): array
    {
        return ['users' => fn ($query) => $query->withTrashed()];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(Role $role, string $event, array $metadata = []): void
    {
        $this->systemActivityService->recordSubjectEvent(
            SystemActivity::SUBJECT_ROLE,
            $role->id,
            $event,
            ['id' => $role->id, 'name' => $role->name, 'color' => $role->color, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()],
            $metadata,
            auth()->id(),
            auth()->user()?->name,
            now(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'color' => $role->color,
            'permissions' => $role->permissions->pluck('name')->sort()->values(),
            'users_count' => $role->users_count ?? 0,
            'protected' => $role->isBuiltIn(),
        ];
    }
}
