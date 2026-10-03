<?php

namespace Tests\Feature\Sync;

use App\Models\RatePlan;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstantPushTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.role' => 'local',
            'sync.node_id' => 'local-test',
            'sync.instant_push' => true,
            'sync.live_api_url' => 'https://live.test',
            'sync.live_token' => 'token',
        ]);
        DB::table('sync_outbox')->delete(); // rows the lazy seed wrote
    }

    public function test_an_outbox_write_pushes_to_live_as_soon_as_the_request_finishes(): void
    {
        $this->fakeLiveAckingEverything();

        RatePlan::create(['name' => 'Instant', 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        $this->finishRequest();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://live.test/api/sync/push');
        $this->assertNotNull(DB::table('sync_outbox')->where('entity_type', 'rate_plan')->value('pushed_at'));
    }

    public function test_nothing_is_pushed_when_the_request_wrote_no_outbox_rows(): void
    {
        $this->fakeLiveAckingEverything();

        $this->finishRequest();

        Http::assertNothingSent();
    }

    public function test_the_live_node_writes_outbox_rows_but_never_pushes(): void
    {
        $this->fakeLiveAckingEverything();
        config(['sync.role' => 'live']);

        RatePlan::create(['name' => 'Live', 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        $this->finishRequest();

        $this->assertDatabaseHas('sync_outbox', ['entity_type' => 'rate_plan']);
        Http::assertNothingSent();
    }

    public function test_a_failed_instant_push_backs_off_and_leaves_retries_to_the_schedule(): void
    {
        Http::fake(['https://live.test/*' => Http::response('down', 503)]);

        RatePlan::create(['name' => 'First', 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        $this->finishRequest();
        RatePlan::create(['name' => 'Second', 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        $this->finishRequest();

        Http::assertSentCount(1);
        $this->assertSame(2, DB::table('sync_outbox')->where('entity_type', 'rate_plan')->whereNull('pushed_at')->count());
    }

    public function test_a_discarded_deferred_callback_does_not_disable_instant_push_for_the_process(): void
    {
        $this->fakeLiveAckingEverything();

        RatePlan::create(['name' => 'Lost', 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        app(DeferredCallbackCollection::class)->forget('sync:push'); // e.g. a nested Artisan::call whose CommandFinished drops the request's callbacks
        RatePlan::create(['name' => 'Next', 'duration_days' => 30, 'price' => 100, 'is_active' => true]);
        $this->finishRequest();

        Http::assertSentCount(1);
        $this->assertSame(0, DB::table('sync_outbox')->whereNull('pushed_at')->count());
    }

    private function fakeLiveAckingEverything(): void
    {
        Http::fake(fn (Request $request) => Http::response([
            'acks' => collect($request->data()['events'])->map(fn ($event) => ['event_id' => $event['event_id'], 'status' => 'ok'])->all(),
        ]));
    }

    /** What InvokeDeferredCallbacks (HTTP) / CommandFinished / JobAttempted do at the end of a cycle. */
    private function finishRequest(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }
}
