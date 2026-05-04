<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbound HTTP client used by the local node to talk to the live API.
 *
 * Centralizes auth header, base URL, timeouts, and lets tests fake
 * responses through Http::fake().
 */
class SyncClient
{
    public function push(array $events): Response
    {
        return $this->request()->post('sync/push', ['events' => $events]);
    }

    public function pull(int $since, int $limit): Response
    {
        return $this->request()->get('sync/pull', [
            'since' => $since,
            'limit' => $limit,
        ]);
    }

    public function snapshot(string $entityType, int $afterId, int $limit): Response
    {
        return $this->request()->get('sync/snapshot/'.$entityType, [
            'after_id' => $afterId,
            'limit' => $limit,
        ]);
    }

    public function heartbeat(string $nodeId): Response
    {
        return $this->request()->post('sync/heartbeat', [
            'node_id' => $nodeId,
        ]);
    }

    private function request(): PendingRequest
    {
        // config('sync.live_api_url') already trims trailing slashes.
        $base = (string) config('sync.live_api_url');
        $token = (string) config('sync.live_token');

        if ($base === '' || $token === '') {
            throw new RuntimeException('Sync client is not configured: set LIVE_API_URL and LIVE_SYNC_TOKEN.');
        }

        return Http::baseUrl($base.'/api/')
            ->timeout((int) config('sync.http_timeout', 30))
            ->acceptJson()
            ->asJson()
            ->withToken($token);
    }
}
