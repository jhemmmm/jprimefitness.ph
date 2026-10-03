<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use App\Models\RatePlan;
use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncReceiverRegistry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NullUuidTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function event(string $op, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'entity_type' => 'rate_plan',
            'entity_id' => (string) ($payload['uuid'] ?? $payload['id'] ?? ''),
            'op' => $op,
            'payload' => $payload,
            'origin_node' => 'remote-test',
            'occurred_at' => now()->toIso8601String(),
        ];
    }

    public function test_receiver_skips_uuid_keyed_rows_that_arrive_without_a_uuid(): void
    {
        config(['sync.role' => 'live']);
        $receiver = app(SyncReceiverRegistry::class)->receiverFor('rate_plan');
        $payload = ['id' => 9, 'uuid' => null, 'name' => 'Monthly', 'duration_days' => 30, 'price' => 1600, 'is_active' => true, 'updated_at' => now()->toIso8601String()];

        $this->assertSame(AckStatus::SKIPPED, $receiver->apply($this->event('create', $payload)));
        $this->assertSame(AckStatus::SKIPPED, $receiver->apply($this->event('update', $payload)));
        $this->assertSame(0, RatePlan::count());
    }

    public function test_backfill_migration_assigns_uuids_to_legacy_rows(): void
    {
        DB::table('rate_plans')->insert([
            ['name' => 'Legacy A', 'duration_days' => 30, 'price' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Legacy B', 'duration_days' => 30, 'price' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->assertSame(2, RatePlan::whereNull('uuid')->count());

        (require database_path('migrations/2026_09_21_100000_backfill_missing_uuids_on_synced_tables.php'))->up();

        $this->assertSame(0, RatePlan::whereNull('uuid')->count());
        $this->assertSame(2, RatePlan::distinct()->count('uuid'));
    }
}
