<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok'
        ]);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'optimizer' => ['status'],
                'llm' => ['status'],
                'storage' => ['status']
            ]
        ]);
    }

    public function test_api_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok'
        ]);
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'optimizer',
                'llm',
                'storage'
            ]
        ]);
    }
}
