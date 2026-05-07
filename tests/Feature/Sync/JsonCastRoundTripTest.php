<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\SystemActivity;
use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncOp;
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
}
