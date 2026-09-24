<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'RAFA Rental System API is running.',
            'version' => 'v1',
        ]);
    }
}
