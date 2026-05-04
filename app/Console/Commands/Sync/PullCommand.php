<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncEventApplier;
use App\Services\Sync\SyncRole;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Console\Command;
use Throwable;

class PullCommand extends Command
{
    protected $signature = 'sync:pull {--limit= : Override per-call batch size} {--max-batches=20 : Safety cap on loop iterations}';

    protected $description = 'Fetch events from the live API and apply them locally.';

    public function handle(SyncClient $client, SyncEventApplier $applier, SyncStateRepository $state): int
    {
        if (config('sync.role') !== SyncRole::LOCAL) {
            $this->warn('sync:pull only runs on a local node (APP_NODE_ROLE=local).');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('sync.pull_batch_size', 500));
        $maxBatches = (int) $this->option('max-batches');

        $totalApplied = 0;
        $cursor = $state->getInt('live_pull_cursor', 0);

        for ($i = 0; $i < $maxBatches; $i++) {
            try {
                $response = $client->pull($cursor, $limit);
            } catch (Throwable $e) {
                $this->error('Pull failed: '.$e->getMessage());
                report($e);

                return self::FAILURE;
            }

            if ($response->failed()) {
                $this->error('Pull HTTP error '.$response->status().': '.$response->body());

                return self::FAILURE;
            }

            $events = $response->json('events', []);

            if (empty($events)) {
                break;
            }

            $acks = $applier->applyBatch($events);

            foreach ($acks as $ack) {
                if (in_array($ack['status'], [AckStatus::OK, AckStatus::CONFLICT], true)) {
                    $totalApplied++;
                }
            }

            $cursor = (int) max(
                $cursor,
                ...array_map(fn ($e) => (int) ($e['sequence'] ?? 0), $events)
            );

            $state->set('live_pull_cursor', (string) $cursor);
            $state->set('last_pull_at', (string) now());

            if (! $response->json('has_more', false)) {
                break;
            }
        }

        $this->info("Applied {$totalApplied} events. Cursor: {$cursor}.");

        return self::SUCCESS;
    }
}
