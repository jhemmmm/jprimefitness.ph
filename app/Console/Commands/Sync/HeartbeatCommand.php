<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncRole;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class HeartbeatCommand extends Command
{
    protected $signature = 'sync:heartbeat';

    protected $description = 'Ping the live API so it knows the local node is alive.';

    public function handle(SyncClient $client, SyncStateRepository $state): int
    {
        if (config('sync.role') !== SyncRole::LOCAL) {
            $this->warn('sync:heartbeat only runs on a local node (APP_NODE_ROLE=local).');

            return self::SUCCESS;
        }

        try {
            $response = $client->heartbeat((string) config('sync.node_id'), $state->getInt('live_pull_cursor', 0));
        } catch (Throwable $e) {
            // offline repeats every minute until the network is back; a stale last_heartbeat_at is the alarm, not the log
            if (! $e instanceof ConnectionException) {
                report($e);
            }

            $this->error('Heartbeat failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error('Heartbeat HTTP error '.$response->status());

            return self::FAILURE;
        }

        $state->set('last_heartbeat_at', (string) now());

        return self::SUCCESS;
    }
}
