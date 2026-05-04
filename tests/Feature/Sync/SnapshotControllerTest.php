<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SnapshotControllerTest extends TestCase
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

    public function test_snapshot_returns_rows_for_an_entity_type(): void
    {
        RatePlan::create([
            'name' => 'Snapshot Plan',
            'duration_days' => 30,
            'price' => 555.00,
            'is_active' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.self::TOKEN,
        ])->getJson('/api/sync/snapshot/rate_plan?after_id=0&limit=100');

        $response->assertOk()
            ->assertJsonPath('entity_type', 'rate_plan')
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'Snapshot Plan')
            ->assertJsonPath('has_more', false);

        $this->assertIsInt($response->json('outbox_max_id'));
    }

    public function test_snapshot_paginates_after_id(): void
    {
        for ($i = 0; $i < 5; $i++) {
            RatePlan::create([
                'name' => 'Plan '.$i,
                'duration_days' => 30,
                'price' => 100.00,
                'is_active' => true,
            ]);
        }

        $first = $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/sync/snapshot/rate_plan?after_id=0&limit=2');

        $first->assertOk()->assertJsonCount(2, 'rows')->assertJsonPath('has_more', true);

        $next = (int) $first->json('next_after_id');

        $second = $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/sync/snapshot/rate_plan?after_id='.$next.'&limit=2');

        $second->assertOk()->assertJsonCount(2, 'rows');
    }

    public function test_snapshot_rejects_unknown_entity_type(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])
            ->getJson('/api/sync/snapshot/notathing?after_id=0')
            ->assertStatus(500);
    }
}
