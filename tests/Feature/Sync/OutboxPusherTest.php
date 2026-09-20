<?php

namespace Tests\Feature\Sync;

use App\Models\RatePlan;
use App\Services\Sync\OutboxPusher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutboxPusherTest extends TestCase
{
    use LazilyRefreshDatabase;

    private bool $liveDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.role' => 'local',
            'sync.node_id' => 'local-test',
            'sync.instant_push' => false,
            'sync.live_api_url' => 'https://live.test',
            'sync.live_token' => 'token',
        ]);
        DB::table('sync_outbox')->delete(); // rows the lazy seed wrote
    }

    public function test_drains_the_outbox_in_batches_until_empty(): void
    {
        $this->fakeLive();
        $this->plans(5);

        $this->assertSame(5, app(OutboxPusher::class)->push(batchSize: 2));

        Http::assertSentCount(3);
        $this->assertSame(0, $this->unpushed()->count());
    }

    public function test_rejected_rows_go_to_the_back_of_the_queue_and_never_block_newer_ones(): void
    {
        $this->fakeLive(rejecting: 'Bad');
        $this->plans(1, 'Bad');
        $this->plans(3);
        $pusher = app(OutboxPusher::class);

        // the stuck row is tried in its first batch and once more after the good rows ran out, then the drain stops
        $this->assertSame(3, $pusher->push(batchSize: 2));
        $this->assertSame(1, $this->unpushed()->count());
        $this->assertSame(2, (int) $this->unpushed()->value('attempts'));
        $this->assertTrue(Cache::has('sync:push:backoff'), 'a head of nothing but rejected rows rests the instant path');

        // newer rows still get through ahead of the stuck one
        Cache::forget('sync:push:backoff');
        $this->plans(2);
        $this->assertSame(2, $pusher->push(batchSize: 2));
        $this->assertSame(3, (int) $this->unpushed()->value('attempts'));
    }

    public function test_a_second_push_skips_while_one_holds_the_lock(): void
    {
        $this->fakeLive();
        $this->plans(1);

        $this->assertTrue(Cache::lock('sync:push', 30)->get());
        $this->assertNull(app(OutboxPusher::class)->push());
        Http::assertNothingSent();
    }

    public function test_sync_push_command_reports_the_outcome(): void
    {
        $this->fakeLive();
        $this->plans(2);

        $this->artisan('sync:push')->expectsOutputToContain('Pushed 2 events.')->assertSuccessful();

        $this->liveDown = true;
        $this->plans(1);
        $this->artisan('sync:push')->expectsOutputToContain('Push HTTP error 503')->assertFailed();
    }

    private function plans(int $count, string $name = 'Plan'): void
    {
        for ($i = 0; $i < $count; $i++) {
            RatePlan::create(['name' => $name, 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        }
    }

    private function unpushed()
    {
        return DB::table('sync_outbox')->whereNull('pushed_at');
    }

    /** Live acks everything except events whose payload name matches $rejecting, or is down entirely. */
    private function fakeLive(?string $rejecting = null): void
    {
        Http::fake(fn (Request $request) => $this->liveDown ? Http::response(str_repeat('x', 5000), 503) : Http::response([
            'acks' => collect($request->data()['events'])->map(fn ($event) => [
                'event_id' => $event['event_id'],
                'status' => $rejecting !== null && ($event['payload']['name'] ?? null) === $rejecting ? 'error' : 'ok',
            ])->all(),
        ]));
    }
}
