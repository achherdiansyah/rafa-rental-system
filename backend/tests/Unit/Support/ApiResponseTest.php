<?php

namespace Tests\Unit\Support;

use App\Support\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_envelope_structure(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Operation succeeded', 200);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Operation succeeded', $data['message']);
        $this->assertEquals(['id' => 1], $data['data']);
    }

    public function test_error_envelope_structure(): void
    {
        $response = ApiResponse::error('Validation failed', ['field' => ['Invalid value']], 422, 'VALIDATION_FAILED');

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals('Validation failed', $data['message']);
        $this->assertEquals(['field' => ['Invalid value']], $data['errors']);
        $this->assertEquals('VALIDATION_FAILED', $data['code']);
    }
}
