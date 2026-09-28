<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Reports\ReportingQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only reporting endpoints. BUSINESS data is never mutated here.
 * Full scope for ADMIN/OWNER; USER receives scoped (own) summaries only.
 */
class ReportingController extends ApiController
{
    public function __construct(
        private readonly ReportingQueryService $service
    ) {}

    public function bookings(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->bookingSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan booking berhasil dimuat.'
        );
    }

    public function rentals(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->rentalSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan rental berhasil dimuat.'
        );
    }

    public function timesheet(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->timesheetSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan timesheet berhasil dimuat.'
        );
    }

    public function financial(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->financialSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan keuangan berhasil dimuat.'
        );
    }

    /**
     * Equipment utilization requires ADMIN/OWNER (cross-customer asset data).
     */
    /**
     * Single-payload admin/owner dashboard (minimises API requests).
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        return $this->success(
            $this->service->dashboard($this->filters($request)),
            'Dashboard operasional berhasil dimuat.'
        );
    }

    public function equipmentUtilization(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        return $this->success(
            $this->service->equipmentUtilization($this->filters($request)),
            'Laporan utilisasi armada berhasil dimuat.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $allowed = ['from', 'to', 'status', 'customer_id', 'project_id', 'model_id', 'rental_id'];

        return collect($allowed)->mapWithKeys(fn ($key) => [
            $key => $request->query($key),
        ])->filter(fn ($v) => $v !== null && $v !== '')->all();
    }

    private function scopeFor(Request $request): ?int
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isAdmin() || $user->isOwner() ? null : (int) $user->id;
    }
}
