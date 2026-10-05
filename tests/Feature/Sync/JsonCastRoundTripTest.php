<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\MemberSubscription;
use App\Models\RatePlan;
use App\Models\SystemActivity;
use App\Models\User;
use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncOp;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class JsonCastRoundTripTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_outbox_payload_contains_decoded_json_for_array_cast_columns(): void
    {
        config(['sync.role' => 'local', 'sync.node_id' => 'local-test']);
        DB::table('sync_outbox')->truncate();

        SystemActivity::create([
            'subject_type' => SystemActivity::SUBJECT_BUSINESS_PROFILE,
            'subject_id' => 1,
            'subject_label' => 'demo',
            'event' => 'demo.test',
            'title' => 'Demo',
            'message' => 'demo',
            'metadata' => ['key' => 'value', 'nested' => ['n' => 1]],
            'occurred_at' => now(),
        ]);

        $row = DB::table('sync_outbox')->where('entity_type', 'system_activity')->first();
        $this->assertNotNull($row);

        $payload = json_decode($row->payload, true);
        $this->assertIsArray($payload['metadata']);
        $this->assertSame('value', $payload['metadata']['key']);
        $this->assertSame(1, $payload['metadata']['nested']['n']);
    }

    public function test_receiver_writes_array_to_db_not_double_encoded_string(): void
    {
        config(['sync.role' => 'live']);

        $uuid = (string) Str::uuid();

        $event = [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'system_activity',
            'entity_id' => $uuid,
            'op' => SyncOp::CREATE,
            'payload' => [
                'uuid' => $uuid,
                'subject_type' => SystemActivity::SUBJECT_BUSINESS_PROFILE,
                'subject_id' => 1,
                'subject_label' => 'demo',
                'event' => 'demo.test',
                'title' => 'Demo',
                'message' => 'demo',
                'metadata' => ['key' => 'value'],
                'occurred_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ];

        $ack = app(SyncEventApplier::class)->applyOne($event);
        $this->assertSame(AckStatus::OK, $ack['status']);

        $stored = SystemActivity::where('uuid', $uuid)->first();
        $this->assertNotNull($stored);
        $this->assertIsArray($stored->metadata);
        $this->assertSame('value', $stored->metadata['key']);
    }

    public function test_receiver_recovers_legacy_event_with_raw_json_string_in_payload(): void
    {
        // Simulates an event emitted by the OLD trait code: metadata
        // arrived on the wire as a raw JSON string instead of an array.
        // Without the defensive decode, fillModel would double-encode.
        config(['sync.role' => 'live']);

        $uuid = (string) Str::uuid();

        $event = [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'system_activity',
            'entity_id' => $uuid,
            'op' => SyncOp::CREATE,
            'payload' => [
                'uuid' => $uuid,
                'subject_type' => SystemActivity::SUBJECT_BUSINESS_PROFILE,
                'subject_id' => 1,
                'subject_label' => 'demo',
                'event' => 'demo.test',
                'title' => 'Demo',
                'message' => 'demo',
                'metadata' => '{"key":"value"}',
                'occurred_at' => now()->toIso8601String(),
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
            'origin_node' => 'remote',
            'occurred_at' => now()->toIso8601String(),
        ];

        $ack = app(SyncEventApplier::class)->applyOne($event);
        $this->assertSame(AckStatus::OK, $ack['status']);

        $stored = SystemActivity::where('uuid', $uuid)->first();
        $this->assertIsArray($stored->metadata);
        $this->assertSame('value', $stored->metadata['key']);
    }

    public function test_membership_cancellation_datetime_is_stored_in_database_format_and_syncs_from_legacy_iso_value(): void
    {
        config(['sync.role' => 'local', 'sync.node_id' => 'local-test']);
        $member = User::factory()->create();
        $plan = RatePlan::create([
            'name' => 'Monthly',
            'duration_days' => 30,
            'price' => 1500,
            'is_active' => true,
        ]);
        $subscription = $member->memberSubscriptions()->create([
            'rate_plan_id' => $plan->id,
            'status' => MemberSubscription::STATUS_ACTIVE,
            'start_date' => '2026-10-05',
            'end_date' => '2026-11-03',
        ]);

        $subscription->update([
            'status' => MemberSubscription::STATUS_CANCELLED,
            'cancelled_at' => Carbon::parse('2026-10-05 20:17:31', config('app.timezone')),
        ]);

        $this->assertSame('2026-10-05 20:17:31', DB::table('member_subscriptions')->where('id', $subscription->id)->value('cancelled_at'));

        $outboxPayload = json_decode(DB::table('sync_outbox')
            ->where('entity_type', 'member_subscription')
            ->where('entity_id', $subscription->uuid)
            ->latest('id')
            ->value('payload'), true);
        $this->assertSame('2026-10-05 20:17:31', $outboxPayload['cancelled_at']);

        config(['sync.role' => 'live']);
        $event = [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'member_subscription',
            'entity_id' => $subscription->uuid,
            'op' => SyncOp::UPDATE,
            'payload' => [
                'uuid' => $subscription->uuid,
                'status' => MemberSubscription::STATUS_CANCELLED,
                'cancelled_at' => '2026-10-05T12:17:31.396130Z',
                'updated_at' => now()->addSecond()->toDateTimeString(),
            ],
            'origin_node' => 'local-test',
            'occurred_at' => now()->toIso8601String(),
        ];

        $ack = app(SyncEventApplier::class)->applyOne($event);

        $this->assertSame(AckStatus::OK, $ack['status']);
        $this->assertSame('2026-10-05 20:17:31', DB::table('member_subscriptions')->where('id', $subscription->id)->value('cancelled_at'));
    }
}
