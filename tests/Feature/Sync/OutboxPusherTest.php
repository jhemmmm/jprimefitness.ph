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

    public function test_rejected_rows_are_retried_in_order_then_parked_so_they_stop_blocking(): void
    {
        $this->fakeLive(rejecting: 'Bad');
        $this->plans(1, 'Bad');
        $this->plans(3);
        $pusher = app(OutboxPusher::class);

        // id order: the bad row goes first, fails once this drain, and the rest still get through
        $this->assertSame(3, $pusher->push(batchSize: 2));
        $this->assertSame(1, $this->unpushed()->count());
        $this->assertSame(1, (int) $this->unpushed()->value('attempts'));
        $this->assertFalse(Cache::has('sync:push:backoff'), 'something got through, so no backoff');

        // a head of nothing but rejected rows rests the instant path
        $this->assertSame(0, $pusher->push(batchSize: 2));
        $this->assertSame(2, (int) $this->unpushed()->value('attempts'));
        $this->assertTrue(Cache::has('sync:push:backoff'));

        Cache::forget('sync:push:backoff');
        DB::table('sync_outbox')->whereNull('pushed_at')->update(['attempts' => OutboxPusher::MAX_ATTEMPTS - 1]);
        $this->plans(2);
        $this->assertSame(2, $pusher->push(batchSize: 2));
        $this->assertSame(1, $pusher->parked(), 'one more rejection parks it');

        // parked rows are skipped entirely until someone re-queues them
        $this->plans(1);
        $this->assertSame(1, $pusher->push(batchSize: 2));
        $this->assertSame(1, $pusher->retryParked());
        $this->assertSame(0, $pusher->parked());
    }

    public function test_a_batch_live_rejects_outright_is_halved_until_the_bad_row_stands_alone(): void
    {
        Http::fake(fn (Request $request) => collect($request->data()['events'])->contains(fn ($e) => $e['payload']['name'] === 'Huge')
            ? Http::response('too large', 413)
            : Http::response(['acks' => collect($request->data()['events'])->map(fn ($e) => ['event_id' => $e['event_id'], 'status' => 'ok'])->all()]));
        $this->plans(2);
        $this->plans(1, 'Huge');
        $this->plans(1);

        $this->assertSame(3, app(OutboxPusher::class)->push(batchSize: 4));

        $this->assertSame(1, $this->unpushed()->count());
        $this->assertSame('Huge', json_decode($this->unpushed()->value('payload'))->name);
        $this->assertSame(1, (int) $this->unpushed()->value('attempts'));
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

        $this->liveDown = false;
        DB::table('sync_outbox')->whereNull('pushed_at')->update(['attempts' => OutboxPusher::MAX_ATTEMPTS]);
        $this->artisan('sync:push')->expectsOutputToContain('1 rows parked')->assertSuccessful();
        $this->artisan('sync:push --retry-parked')->expectsOutputToContain('Pushed 1 events.')->assertSuccessful();
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
