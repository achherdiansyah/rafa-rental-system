<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_endpoint_returns_expected_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'success' => true,
                'message' => 'RAFA Rental System API is running.',
                'version' => 'v1',
            ]);
    }
}
