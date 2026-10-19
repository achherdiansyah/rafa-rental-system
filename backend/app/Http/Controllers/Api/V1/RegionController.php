<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Services\Region\RegionService;
use Illuminate\Http\JsonResponse;
use Throwable;

class RegionController extends ApiController
{
    public function __construct(private readonly RegionService $regions) {}

    public function provinces(): JsonResponse
    {
        return $this->respond(fn () => $this->regions->provinces());
    }

    public function regencies(string $province): JsonResponse
    {
        return $this->respond(fn () => $this->regions->regencies($province));
    }

    public function districts(string $regency): JsonResponse
    {
        return $this->respond(fn () => $this->regions->districts($regency));
    }

    public function villages(string $district): JsonResponse
    {
        return $this->respond(fn () => $this->regions->villages($district));
    }

    private function respond(callable $loader): JsonResponse
    {
        try {
            return $this->success($loader(), 'Data wilayah berhasil dimuat.');
        } catch (Throwable) {
            return $this->error(
                'Gagal memuat data wilayah. Periksa koneksi lalu coba lagi.',
                [],
                503,
                'REGION_SERVICE_UNAVAILABLE'
            );
        }
    }
}
