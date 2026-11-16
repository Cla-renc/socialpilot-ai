<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_readiness_endpoints_are_available(): void
    {
        $health = $this->getJson('/api/v1/health');

        $health->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database',
                    'users',
                    'brands',
                ],
            ]);

        $ready = $this->getJson('/api/v1/health/ready');

        $ready->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonStructure(['ready', 'service', 'timestamp']);
    }
}
