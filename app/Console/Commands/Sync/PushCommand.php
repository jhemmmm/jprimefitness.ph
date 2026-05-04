<?php

declare(strict_types=1);

namespace App\Console\Commands\Sync;

use App\Services\Sync\AckStatus;
use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncRole;
use App\Services\Sync\SyncStateRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PushCommand extends Command
{
    protected $signature = 'sync:push {--limit= : Override the per-call batch size}';

    protected $description = 'Drain the local sync_outbox to the live API.';

    public function handle(SyncClient $client, SyncStateRepository $state): int
    {
        if (config('sync.role') !== SyncRole::LOCAL) {
            $this->warn('sync:push only runs on a local node (APP_NODE_ROLE=local).');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('sync.push_batch_size', 200));

        $totalPushed = 0;

        while (true) {
            $rows = DB::table('sync_outbox')
                ->whereNull('pushed_at')
                ->orderBy('id')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $events = $rows->map(fn ($row) => [
                'event_id' => $row->event_id,
                'entity_type' => $row->entity_type,
                'entity_id' => $row->entity_id,
                'op' => $row->op,
                'payload' => json_decode($row->payload, true) ?? [],
                'origin_node' => $row->origin_node,
                'occurred_at' => $row->occurred_at,
            ])->all();

            try {
                $response = $client->push($events);
            } catch (Throwable $e) {
                $this->error('Push failed: '.$e->getMessage());
                report($e);

                return self::FAILURE;
            }

            if ($response->failed()) {
                $this->error('Push HTTP error '.$response->status().': '.$response->body());

                return self::FAILURE;
            }

            $acks = collect($response->json('acks', []))->keyBy('event_id');
            $terminalStatuses = [AckStatus::OK, AckStatus::CONFLICT, AckStatus::SKIPPED];

            $drainedIds = $rows
                ->filter(function ($row) use ($acks, $terminalStatuses) {
                    $ack = $acks->get($row->event_id);

                    return $ack && in_array($ack['status'] ?? '', $terminalStatuses, true);
                })
                ->pluck('id');

            // Errors stay un-acked so the next tick retries them.
            if ($drainedIds->isNotEmpty()) {
                DB::table('sync_outbox')
                    ->whereIn('id', $drainedIds)
                    ->update(['pushed_at' => now(), 'updated_at' => now()]);
                $totalPushed += $drainedIds->count();
            }
        }

        $state->set('last_push_at', (string) now());

        $this->info("Pushed {$totalPushed} events.");

        return self::SUCCESS;
    }
}
