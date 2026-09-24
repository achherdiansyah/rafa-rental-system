<?php

namespace Tests\Feature\Api;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionHandlingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Setup dummy routes to trigger exceptions
        Route::get('/api/test-404', function () {
            abort(404);
        });

        Route::get('/api/test-business', function () {
            throw new BusinessRuleException('Cannot do this.');
        });

        Route::get('/api/test-state', function () {
            throw new InvalidStateTransitionException('Invalid state.');
        });

        Route::get('/api/test-500', function () {
            throw new \Exception('Secret error message');
        });
    }

    public function test_404_not_found_returns_standard_format(): void
    {
        $response = $this->getJson('/api/test-404');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Resource not found.',
                'code' => 'RESOURCE_NOT_FOUND',
            ]);
    }

    public function test_business_exception_returns_409_with_code(): void
    {
        $response = $this->getJson('/api/test-business');

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot do this.',
                'code' => 'BUSINESS_RULE_VIOLATION',
            ]);
    }

    public function test_invalid_state_transition_returns_409_with_code(): void
    {
        $response = $this->getJson('/api/test-state');

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid state.',
                'code' => 'INVALID_STATE_TRANSITION',
            ]);
    }

    public function test_500_hides_details_in_production(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/test-500');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'An internal server error occurred.',
                'code' => 'INTERNAL_SERVER_ERROR',
            ]);

        // errors key exists but carries no internal detail in production
        $this->assertEmpty($response->json('errors'));
    }

    public function test_500_shows_details_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/api/test-500');

        $response->assertStatus(500)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['exception', 'file', 'line'],
                'code',
            ]);

        $this->assertEquals('Secret error message', $response->json('message'));
    }
}
