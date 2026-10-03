<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\OutboxPusher;
use App\Services\Sync\SyncRole;
use Illuminate\Console\Command;
use Throwable;

class PushCommand extends Command
{
    protected $signature = 'sync:push {--limit= : Override the per-call batch size} {--retry-parked : Re-queue rows live rejected '.OutboxPusher::MAX_ATTEMPTS.' times}';

    protected $description = 'Drain the local sync_outbox to the live API.';

    public function handle(OutboxPusher $pusher): int
    {
        if (config('sync.role') !== SyncRole::LOCAL) {
            $this->warn('sync:push only runs on a local node (APP_NODE_ROLE=local).');

            return self::SUCCESS;
        }

        if ($this->option('retry-parked')) {
            $this->info("Re-queued {$pusher->retryParked()} parked rows.");
        }

        try {
            $pushed = $pusher->push($this->option('limit') ? (int) $this->option('limit') : null);
        } catch (Throwable $e) {
            // every 30 s while live is down; the staleness of sync_state.last_push_at is the alarm, not the log
            $this->error('Push failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($parked = $pusher->parked()) {
            $this->warn("{$parked} rows parked after repeated rejection; fix the cause, then sync:push --retry-parked.");
        }

        if ($pushed === null) {
            $this->info('Another sync:push is running; skipping.');

            return self::SUCCESS;
        }

        $this->info("Pushed {$pushed} events.");

        return self::SUCCESS;
    }
}
