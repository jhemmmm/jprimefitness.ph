<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applies one or more inbound sync events to the local database.
 *
 * Centralizes the (sync_inbox dedup → transactional apply →
 * inbox-insert) pipeline shared by the push controller and the pull
 * command, including bulk-prefetch of dedup state per batch.
 */
class SyncEventApplier
{
    public function __construct(private SyncReceiverRegistry $registry) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array{event_id:string,status:string,message?:string}>
     */
    public function applyBatch(array $events): array
    {
        if ($events === []) {
            return [];
        }

        $alreadyApplied = $this->dedupSet($events);

        $acks = [];

        foreach ($events as $event) {
            $key = $event['origin_node'].'|'.$event['event_id'];

            if (isset($alreadyApplied[$key])) {
                $acks[] = [
                    'event_id' => $event['event_id'],
                    'status' => AckStatus::OK,
                    'message' => 'replayed',
                ];

                continue;
            }

            $acks[] = $this->applyOne($event);
        }

        return $acks;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{event_id:string,status:string,message?:string}
     */
    public function applyOne(array $event): array
    {
        try {
            $receiver = $this->registry->receiverFor($event['entity_type']);

            return DB::transaction(function () use ($event, $receiver) {
                $status = $receiver->apply($event);

                DB::table('sync_inbox')->insert([
                    'origin_node' => $event['origin_node'],
                    'event_id' => $event['event_id'],
                    'entity_type' => $event['entity_type'],
                    'entity_id' => $event['entity_id'],
                    'op' => $event['op'],
                    'status' => $status,
                    'applied_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return [
                    'event_id' => $event['event_id'],
                    'status' => $status,
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'event_id' => $event['event_id'],
                'status' => AckStatus::ERROR,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Bulk-fetch sync_inbox rows for the batch in a single query, keyed
     * by "origin_node|event_id" so each event's dedup check is an
     * O(1) lookup.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, true>
     */
    private function dedupSet(array $events): array
    {
        $byOrigin = Collection::make($events)
            ->groupBy('origin_node')
            ->map(fn (Collection $group) => $group->pluck('event_id')->all())
            ->all();

        $found = [];

        foreach ($byOrigin as $origin => $eventIds) {
            $rows = DB::table('sync_inbox')
                ->where('origin_node', $origin)
                ->whereIn('event_id', $eventIds)
                ->pluck('event_id');

            foreach ($rows as $id) {
                $found[$origin.'|'.$id] = true;
            }
        }

        return $found;
    }
}
