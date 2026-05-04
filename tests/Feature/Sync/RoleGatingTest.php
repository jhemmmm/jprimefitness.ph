<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RoleGatingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_routes_404_when_role_is_local(): void
    {
        config([
            'sync.role' => 'local',
            'sync.live_token' => 'token',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/sync/pull?since=0')
            ->assertStatus(404);

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/sync/push', ['events' => []])
            ->assertStatus(404);

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->postJson('/api/sync/heartbeat')
            ->assertStatus(404);
    }

    public function test_sync_routes_404_when_role_is_standalone(): void
    {
        config([
            'sync.role' => 'standalone',
            'sync.live_token' => 'token',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer token'])
            ->getJson('/api/sync/pull?since=0')
            ->assertStatus(404);
    }

    public function test_sync_routes_503_when_token_not_set_on_live(): void
    {
        config([
            'sync.role' => 'live',
            'sync.live_token' => null,
        ]);

        $this->postJson('/api/sync/push', ['events' => []])
            ->assertStatus(503);
    }
}
