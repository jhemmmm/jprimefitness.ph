<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\OutboxWriter;
use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncOp;
use App\Services\Sync\SyncReceiverRegistry;
use App\Services\Sync\SyncRole;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-time seed of a freshly-installed local instance.
 *
 *   - Iterates the entity-type list from SyncReceiverRegistry in
 *     dependency order.
 *   - Pages through /api/sync/snapshot/{type} until exhausted.
 *   - Upserts each row through the same receiver pipeline used for
 *     incremental events. Receivers wrap saves in OutboxWriter::mute()
 *     so the bootstrap doesn't re-emit everything we just imported.
 *   - Captures the live outbox max-id and writes it to
 *     sync_state.live_pull_cursor so subsequent sync:pull invocations
 *     pick up only new events.
 */
class BootstrapCommand extends Command
{
    protected $signature = 'sync:bootstrap {--limit=500 : Page size}';

    protected $description = 'Seed this local instance from the live snapshot endpoint.';

    public function handle(SyncClient $client, SyncReceiverRegistry $registry, SyncStateRepository $state): int
    {
        if (config('sync.role') !== SyncRole::LOCAL) {
            $this->warn('sync:bootstrap only runs on a local node (APP_NODE_ROLE=local).');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $entityTypes = $registry->entityTypesInOrder();
        $highestOutboxId = 0;

        foreach ($entityTypes as $entityType) {
            $this->info("Bootstrapping {$entityType}...");

            $afterId = 0;
            $count = 0;

            while (true) {
                try {
                    $response = $client->snapshot($entityType, $afterId, $limit);
                } catch (Throwable $e) {
                    report($e);
                    $this->error("  HTTP error: {$e->getMessage()}");

                    return self::FAILURE;
                }

                if ($response->failed()) {
                    $this->error("  HTTP error {$response->status()}: ".$response->body());

                    return self::FAILURE;
                }

                $rows = $response->json('rows', []);
                $highestOutboxId = max($highestOutboxId, (int) $response->json('outbox_max_id', 0));

                if (empty($rows)) {
                    break;
                }

                $receiver = $registry->receiverFor($entityType);

                foreach ($rows as $row) {
                    OutboxWriter::mute(function () use ($receiver, $row, $entityType) {
                        $receiver->apply([
                            'event_id' => (string) Str::uuid(),
                            'entity_type' => $entityType,
                            'entity_id' => (string) ($row['entity_id'] ?? $row['uuid'] ?? $row['id'] ?? ''),
                            'op' => SyncOp::CREATE,
                            'payload' => $row,
                            'origin_node' => 'bootstrap',
                            'occurred_at' => $row['updated_at'] ?? $row['created_at'] ?? (string) now(),
                        ]);
                    });

                    $count++;
                }

                $afterId = (int) $response->json('next_after_id', $afterId);

                if (! $response->json('has_more', false)) {
                    break;
                }
            }

            $this->line("  imported {$count} rows.");
        }

        $state->set('live_pull_cursor', (string) $highestOutboxId);
        $state->set('last_snapshot_at', (string) now());

        $this->info("Bootstrap complete. live_pull_cursor = {$highestOutboxId}.");

        return self::SUCCESS;
    }
}
