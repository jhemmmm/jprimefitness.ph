<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\OutboxPusher;
use App\Services\Sync\SyncRole;
use Illuminate\Console\Command;
use Throwable;

class PushCommand extends Command
{
    protected $signature = 'sync:push {--limit= : Override the per-call batch size}';

    protected $description = 'Drain the local sync_outbox to the live API.';

    public function handle(OutboxPusher $pusher): int
    {
        if (config('sync.role') !== SyncRole::LOCAL) {
            $this->warn('sync:push only runs on a local node (APP_NODE_ROLE=local).');

            return self::SUCCESS;
        }

        try {
            $pushed = $pusher->push($this->option('limit') ? (int) $this->option('limit') : null);
        } catch (Throwable $e) {
            $this->error('Push failed: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        if ($pushed === null) {
            $this->info('Another sync:push is running; skipping.');

            return self::SUCCESS;
        }

        $this->info("Pushed {$pushed} events.");

        return self::SUCCESS;
    }
}
