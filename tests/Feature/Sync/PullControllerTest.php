<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PullControllerTest extends TestCase
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

    private function pull(int $since = 0, int $limit = 100): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders([
            'Authorization' => 'Bearer '.self::TOKEN,
        ])->getJson('/api/sync/pull?since='.$since.'&limit='.$limit);
    }

    public function test_pull_returns_events_after_cursor(): void
    {
        // Switch to local-emit mode so creating models writes outbox rows.
        config(['sync.role' => 'local']);

        RatePlan::create([
            'name' => 'Pull Plan A',
            'duration_days' => 30,
            'price' => 100.00,
            'is_active' => true,
        ]);

        RatePlan::create([
            'name' => 'Pull Plan B',
            'duration_days' => 60,
            'price' => 200.00,
            'is_active' => true,
        ]);

        // Switch back to live so the pull endpoint serves.
        config(['sync.role' => 'live']);

        $this->pull(0, 100)
            ->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.entity_type', 'rate_plan')
            ->assertJsonPath('has_more', false);
    }

    public function test_pull_paginates_via_cursor(): void
    {
        config(['sync.role' => 'local']);

        for ($i = 0; $i < 3; $i++) {
            RatePlan::create([
                'name' => 'Plan '.$i,
                'duration_days' => 30,
                'price' => 100.00,
                'is_active' => true,
            ]);
        }

        config(['sync.role' => 'live']);

        $first = $this->pull(0, 2)->assertOk();
        $first->assertJsonCount(2, 'events');
        $first->assertJsonPath('has_more', true);

        $cursor = $first->json('next_cursor');
        $second = $this->pull($cursor, 2)->assertOk();
        $second->assertJsonCount(1, 'events');
        $second->assertJsonPath('has_more', false);
    }

    public function test_pull_rejects_invalid_token(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer wrong'])
            ->getJson('/api/sync/pull?since=0&limit=10')
            ->assertStatus(401);
    }
}
