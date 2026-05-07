<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\User;
use App\Services\Sync\Receivers\ModelHasRoleReceiver;
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
