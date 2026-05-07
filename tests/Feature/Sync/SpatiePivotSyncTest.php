<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Sync\Receivers\ModelHasRoleReceiver;
use App\Services\Sync\Receivers\PermissionReceiver;
use App\Services\Sync\Receivers\RoleHasPermissionReceiver;
use App\Services\Sync\Receivers\RoleReceiver;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncOp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SpatiePivotSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'sync-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.role' => 'local',
            'sync.node_id' => 'local-test',
            'sync.live_token' => self::TOKEN,
            'permission.events_enabled' => true,
        ]);

        $this->seed(RoleSeeder::class);
    }

    public function test_assign_role_emits_outbox_event_keyed_by_uuid_and_role_name(): void
    {
        $user = User::factory()->create();
        DB::table('sync_outbox')->truncate();

        $user->assignRole('super admin');

        $row = DB::table('sync_outbox')->where('entity_type', 'model_has_roles')->first();

        $this->assertNotNull($row, 'expected an outbox row for the role assignment');
        $this->assertSame('create', $row->op);
        $this->assertSame('local-test', $row->origin_node);

        $payload = json_decode($row->payload, true);
        $this->assertSame('super admin', $payload['role_name']);
        $this->assertSame(User::class, $payload['model_type']);
        $this->assertSame($user->uuid, $payload['model_uuid']);
        $this->assertSame(
            ModelHasRoleReceiver::entityId($user->uuid, User::class, 'super admin', 'web'),
            $row->entity_id
        );
    }

    public function test_remove_role_emits_a_delete_event(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        DB::table('sync_outbox')->truncate();

        $user->removeRole('admin');

        $row = DB::table('sync_outbox')->where('entity_type', 'model_has_roles')->first();
        $this->assertNotNull($row);
        $this->assertSame('delete', $row->op);
        $this->assertSame(
            ModelHasRoleReceiver::entityId($user->uuid, User::class, 'admin', 'web'),
            $row->entity_id
        );
    }

    public function test_sync_roles_diff_emits_one_event_per_change(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'staff']);
        DB::table('sync_outbox')->truncate();

        $user->syncRoles(['manager', 'staff']);

        $events = DB::table('sync_outbox')->where('entity_type', 'model_has_roles')->get();

        $this->assertGreaterThanOrEqual(2, $events->count());

        $detached = $events->where('op', 'delete')->map(fn ($r) => json_decode($r->payload, true)['role_name'])->all();
        $attached = $events->where('op', 'create')->map(fn ($r) => json_decode($r->payload, true)['role_name'])->all();

        $this->assertContains('admin', $detached);
        $this->assertContains('manager', $attached);
    }

    public function test_receiver_applies_create_event_and_grants_role(): void
    {
        // Configure as the live (receiving) node so we can mimic an
        // inbound push from a different instance.
        config(['sync.role' => 'live']);

        $localUser = User::factory()->create();
        DB::table('sync_outbox')->truncate();

        $event = [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'model_has_roles',
            'entity_id' => ModelHasRoleReceiver::entityId($localUser->uuid, User::class, 'super admin', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'role_name' => 'super admin',
                'guard_name' => 'web',
                'model_uuid' => $localUser->uuid,
                'model_type' => User::class,
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ];

        app(SyncEventApplier::class)->applyOne($event);

        $this->assertTrue($localUser->fresh()->hasRole('super admin'));

        // Apply must not bounce a fresh outbox row back to the sender.
        $this->assertSame(0, DB::table('sync_outbox')->where('entity_type', 'model_has_roles')->count());
    }

    public function test_receiver_applies_delete_event_and_revokes_role(): void
    {
        config(['sync.role' => 'live']);

        $localUser = User::factory()->create();
        $localUser->assignRole('admin');
        DB::table('sync_outbox')->truncate();

        $event = [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'model_has_roles',
            'entity_id' => ModelHasRoleReceiver::entityId($localUser->uuid, User::class, 'admin', 'web'),
            'op' => SyncOp::DELETE,
            'payload' => [
                'role_name' => 'admin',
                'guard_name' => 'web',
                'model_uuid' => $localUser->uuid,
                'model_type' => User::class,
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ];

        app(SyncEventApplier::class)->applyOne($event);

        $this->assertFalse($localUser->fresh()->hasRole('admin'));
    }

    public function test_receiver_skips_when_target_user_does_not_exist_locally(): void
    {
        config(['sync.role' => 'live']);

        $event = [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'model_has_roles',
            'entity_id' => ModelHasRoleReceiver::entityId('00000000-0000-0000-0000-000000000000', User::class, 'super admin', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'role_name' => 'super admin',
                'guard_name' => 'web',
                'model_uuid' => '00000000-0000-0000-0000-000000000000',
                'model_type' => User::class,
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ];

        $ack = app(SyncEventApplier::class)->applyOne($event);

        $this->assertSame('skipped', $ack['status']);
    }

    public function test_role_receiver_creates_role_locally(): void
    {
        config(['sync.role' => 'live']);
        DB::table('roles')->where('name', 'gate-tester')->delete();

        app(SyncEventApplier::class)->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'role',
            'entity_id' => RoleReceiver::entityId('gate-tester', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'name' => 'gate-tester',
                'guard_name' => 'web',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'entity_id' => RoleReceiver::entityId('gate-tester', 'web'),
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->assertDatabaseHas('roles', ['name' => 'gate-tester', 'guard_name' => 'web']);
    }

    public function test_permission_receiver_creates_permission_locally(): void
    {
        config(['sync.role' => 'live']);
        DB::table('permissions')->where('name', 'audit-everything')->delete();

        app(SyncEventApplier::class)->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'permission',
            'entity_id' => PermissionReceiver::entityId('audit-everything', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'name' => 'audit-everything',
                'guard_name' => 'web',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'entity_id' => PermissionReceiver::entityId('audit-everything', 'web'),
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->assertDatabaseHas('permissions', ['name' => 'audit-everything', 'guard_name' => 'web']);
    }

    public function test_role_has_permissions_receiver_links_role_to_permission(): void
    {
        config(['sync.role' => 'live']);

        // Pre-existing local role + permission (already seeded).
        $role = Role::where('name', 'staff')->firstOrFail();
        $permission = Permission::where('name', 'access panel')->firstOrFail();
        DB::table('role_has_permissions')->where([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ])->delete();

        app(SyncEventApplier::class)->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'role_has_permissions',
            'entity_id' => RoleHasPermissionReceiver::entityId('staff', 'web', 'access panel', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'role_name' => 'staff',
                'role_guard' => 'web',
                'permission_name' => 'access panel',
                'permission_guard' => 'web',
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_role_assignment_resolves_after_role_is_synced_first(): void
    {
        // Mimic the bootstrap order: role lands before model_has_roles.
        // This is the regression case the user hit when bootstrapping a
        // freshly-migrated DB without running RoleSeeder.
        config(['sync.role' => 'live']);

        DB::table('roles')->where('name', 'auditor')->delete();
        $user = User::factory()->create();

        $applier = app(SyncEventApplier::class);

        $applier->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'role',
            'entity_id' => RoleReceiver::entityId('auditor', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'name' => 'auditor',
                'guard_name' => 'web',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $applier->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'model_has_roles',
            'entity_id' => ModelHasRoleReceiver::entityId($user->uuid, User::class, 'auditor', 'web'),
            'op' => SyncOp::CREATE,
            'payload' => [
                'role_name' => 'auditor',
                'guard_name' => 'web',
                'model_uuid' => $user->uuid,
                'model_type' => User::class,
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->assertTrue($user->fresh()->hasRole('auditor'));
    }

    public function test_creating_a_role_emits_an_outbox_event_with_uuid(): void
    {
        DB::table('sync_outbox')->truncate();

        $role = Role::create(['name' => 'incident-responder']);

        $this->assertNotEmpty($role->uuid);

        $row = DB::table('sync_outbox')
            ->where('entity_type', 'role')
            ->where('entity_id', $role->uuid)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('create', $row->op);

        $payload = json_decode($row->payload, true);
        $this->assertSame('incident-responder', $payload['name']);
        $this->assertSame($role->uuid, $payload['uuid']);
    }

    public function test_creating_a_permission_emits_an_outbox_event_with_uuid(): void
    {
        DB::table('sync_outbox')->truncate();

        $permission = Permission::create(['name' => 'restart-cluster']);

        $row = DB::table('sync_outbox')
            ->where('entity_type', 'permission')
            ->where('entity_id', $permission->uuid)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('create', $row->op);
    }

    public function test_role_give_permission_emits_role_has_permissions_event_not_model_has_permissions(): void
    {
        $role = Role::where('name', 'staff')->firstOrFail();
        $permission = Permission::where('name', 'access panel')->firstOrFail();
        $role->revokePermissionTo($permission);
        DB::table('sync_outbox')->truncate();

        $role->givePermissionTo($permission);

        $rolePivotRow = DB::table('sync_outbox')->where('entity_type', 'role_has_permissions')->first();
        $this->assertNotNull($rolePivotRow, 'expected a role_has_permissions outbox row');
        $this->assertSame('create', $rolePivotRow->op);

        $payload = json_decode($rolePivotRow->payload, true);
        $this->assertSame('staff', $payload['role_name']);
        $this->assertSame('access panel', $payload['permission_name']);

        $this->assertSame(0, DB::table('sync_outbox')->where('entity_type', 'model_has_permissions')->count());
    }

    public function test_role_receiver_converges_legacy_row_to_remote_uuid(): void
    {
        // Mimic a row that pre-dates the uuid migration (post-backfill,
        // local has its own uuid that differs from live's).
        config(['sync.role' => 'live']);
        $localUuid = (string) Str::uuid();
        $remoteUuid = (string) Str::uuid();
        DB::table('roles')->where('name', 'staff')->update(['uuid' => $localUuid]);

        app(SyncEventApplier::class)->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'role',
            'entity_id' => $remoteUuid,
            'op' => SyncOp::CREATE,
            'payload' => [
                'uuid' => $remoteUuid,
                'name' => 'staff',
                'guard_name' => 'web',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $row = DB::table('roles')->where('name', 'staff')->first();
        $this->assertSame($remoteUuid, $row->uuid, 'local uuid did not converge to remote');
        $this->assertSame(1, DB::table('roles')->where('name', 'staff')->count(), 'duplicate row created');
    }

    public function test_role_receiver_creates_row_with_remote_uuid_when_absent(): void
    {
        config(['sync.role' => 'live']);
        DB::table('roles')->where('name', 'compliance-officer')->delete();
        $remoteUuid = (string) Str::uuid();

        app(SyncEventApplier::class)->applyOne([
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'role',
            'entity_id' => $remoteUuid,
            'op' => SyncOp::CREATE,
            'payload' => [
                'uuid' => $remoteUuid,
                'name' => 'compliance-officer',
                'guard_name' => 'web',
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ]);

        $this->assertDatabaseHas('roles', ['uuid' => $remoteUuid, 'name' => 'compliance-officer']);
    }

    public function test_snapshot_endpoint_returns_role_assignments(): void
    {
        config([
            'sync.role' => 'live',
            'sync.live_token' => self::TOKEN,
        ]);

        $user = User::factory()->create();
        $user->assignRole('manager');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/sync/snapshot/model_has_roles?after_id=0&limit=100');

        $response->assertOk()
            ->assertJsonPath('entity_type', 'model_has_roles')
            ->assertJsonPath('has_more', false);

        $rows = collect($response->json('rows'));
        $match = $rows->firstWhere('model_uuid', $user->uuid);

        $this->assertNotNull($match, 'snapshot did not include the assignment');
        $this->assertSame('manager', $match['role_name']);
        $this->assertSame('web', $match['guard_name']);
        $this->assertSame(User::class, $match['model_type']);
        $this->assertSame(
            ModelHasRoleReceiver::entityId($user->uuid, User::class, 'manager', 'web'),
            $match['entity_id']
        );
    }
}
