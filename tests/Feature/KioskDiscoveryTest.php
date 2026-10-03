<?php

namespace Tests\Feature;

use Tests\TestCase;

class KioskDiscoveryTest extends TestCase
{
    public function test_discover_endpoint_is_unauthenticated_and_returns_service_descriptor(): void
    {
        $response = $this->getJson('/api/kiosk/discover');

        $response->assertOk()
            ->assertJsonStructure(['service', 'version', 'serverId'])
            ->assertJsonPath('service', 'jprimefitness-kiosk-api')
            ->assertJsonPath('version', '1');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response->json('serverId'),
        );
    }

    public function test_discover_server_id_is_stable_across_calls(): void
    {
        $first = $this->getJson('/api/kiosk/discover')->json('serverId');
        $second = $this->getJson('/api/kiosk/discover')->json('serverId');

        $this->assertSame($first, $second);
    }
}
