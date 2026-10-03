<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PushControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOKEN = 'sync-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.role' => 'live',
            'sync.live_token' => self::TOKEN,
        ]);
    }

    private function pushEvents(array $events, ?string $token = self::TOKEN): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/sync/push', ['events' => $events]);
    }

    private function buildEvent(string $entityType, string $op, array $payload, string $eventId = ''): array
    {
        return [
            'event_id' => $eventId ?: (string) Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $payload['uuid'] ?? 'unknown',
            'op' => $op,
            'payload' => $payload,
            'origin_node' => 'local-test',
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_push_creates_a_new_row_via_receiver(): void
    {
        $event = $this->buildEvent('rate_plan', 'create', [
            'uuid' => (string) Str::uuid(),
            'name' => 'Pushed Plan',
            'duration_days' => 30,
            'price' => 999.00,
            'is_active' => true,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->pushEvents([$event])->assertOk()
            ->assertJsonPath('acks.0.status', 'ok');

        $this->assertDatabaseHas('rate_plans', [
            'uuid' => $event['payload']['uuid'],
            'name' => 'Pushed Plan',
        ]);
    }

    public function test_push_dedupes_replays_via_inbox(): void
    {
        $event = $this->buildEvent('rate_plan', 'create', [
            'uuid' => (string) Str::uuid(),
            'name' => 'Dedup Plan',
            'duration_days' => 30,
            'price' => 999.00,
            'is_active' => true,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->pushEvents([$event])->assertOk();
        $this->pushEvents([$event])->assertOk()
            ->assertJsonPath('acks.0.message', 'replayed');

        $this->assertSame(1, RatePlan::where('uuid', $event['payload']['uuid'])->count());
        $this->assertSame(1, DB::table('sync_inbox')->count());
    }

    public function test_push_drops_stale_update_with_conflict_status(): void
    {
        $plan = RatePlan::create([
            'name' => 'Local Plan',
            'duration_days' => 30,
            'price' => 100.00,
            'is_active' => true,
        ]);

        // Touch the model so updated_at is "now".
        $plan->update(['price' => 150.00]);

        $event = $this->buildEvent('rate_plan', 'update', [
            'uuid' => $plan->uuid,
            'name' => 'Stale Remote Edit',
            'duration_days' => 30,
            'price' => 50.00,
            'is_active' => true,
            'created_at' => $plan->created_at->toIso8601String(),
            'updated_at' => now()->subHour()->toIso8601String(),
        ]);

        $this->pushEvents([$event])->assertOk()
            ->assertJsonPath('acks.0.status', 'conflict');

        $this->assertSame(150.00, (float) $plan->fresh()->price);

        $this->assertDatabaseHas('system_activities', [
            'event' => 'sync.conflict_dropped',
        ]);
    }

    public function test_push_rejects_invalid_token(): void
    {
        $event = $this->buildEvent('rate_plan', 'create', [
            'uuid' => (string) Str::uuid(),
            'name' => 'Hacker Plan',
            'duration_days' => 30,
            'price' => 1.00,
            'is_active' => true,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);

        $this->pushEvents([$event], 'wrong-token')->assertStatus(401);
        $this->assertDatabaseMissing('rate_plans', ['name' => 'Hacker Plan']);
    }

    public function test_push_validates_required_fields(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->postJson('/api/sync/push', ['events' => [['event_id' => '']]])
            ->assertStatus(422);
    }
}
