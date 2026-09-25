<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Pricing\CalculatePricingRequest;
use App\Services\Pricing\PricingCalculatorService;
use Illuminate\Http\JsonResponse;

class PricingCalculationController extends ApiController
{
    /**
     * Calculate/Simulate booking total estimate.
     */
    public function __invoke(CalculatePricingRequest $request, PricingCalculatorService $service): JsonResponse
    {
        $inputs = $request->toLineItemInputs();
        $result = $service->calculateBooking($inputs);

        return $this->success($result->toArray(), 'Perhitungan estimasi sewa berhasil.');
    }
}
