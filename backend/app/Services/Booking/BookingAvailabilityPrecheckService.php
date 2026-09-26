<?php

namespace App\Services\Booking;

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Services\Equipment\EquipmentAvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class BookingAvailabilityPrecheckService
{
    public function __construct(
        protected EquipmentAvailabilityService $availabilityService
    ) {}

    /**
     * Validate that requested aggregate quantities per equipment model are available
     * during the booking's overall period. This is a pre-check (soft check) and
     * does NOT create any reservation or lock.
     *
     * @param  array<int, array{equipment_model_id: int, quantity: int}>  $lines
     *
     * @throws BusinessRuleException
     */
    public function assertAvailable(array $lines, CarbonInterface $startDate, CarbonInterface $endDate): void
    {
        if (empty($lines)) {
            return;
        }

        // Aggregate requested quantity per model across the booking
        /** @var array<int, int> $requestedByModel */
        $requestedByModel = [];
        foreach ($lines as $line) {
            $modelId = (int) $line['equipment_model_id'];
            $qty = max(1, (int) ($line['quantity'] ?? 1));
            $requestedByModel[$modelId] = ($requestedByModel[$modelId] ?? 0) + $qty;
        }

        // Bulk availability map for all requested models at once (no N+1)
        $modelIds = array_keys($requestedByModel);
        $availabilityMap = $this->availabilityService->getAvailabilityMap($modelIds, $startDate, $endDate);

        foreach ($requestedByModel as $modelId => $requestedQty) {
            $available = (int) $availabilityMap->get($modelId, 0);
            if ($available < $requestedQty) {
                throw new BusinessRuleException(
                    "Ketersediaan armada untuk model ID #{$modelId} tidak mencukupi: dibutuhkan {$requestedQty} unit, tersedia {$available} unit pada periode tersebut."
                );
            }
        }
    }

    /**
     * Validate a booking's own detail lines against current availability.
     *
     * @throws BusinessRuleException
     */
    public function assertBookingAvailable(Booking $booking): void
    {
        $booking->loadMissing('details');

        if ($booking->details->isEmpty()) {
            return;
        }

        $start = $booking->details->min('start_date');
        $end = $booking->details->max('end_date');

        if (! $start || ! $end) {
            return;
        }

        $lines = $booking->details->map(fn ($d) => [
            'equipment_model_id' => $d->equipment_model_id,
            'quantity' => $d->quantity,
        ])->all();

        $this->assertAvailable(
            $lines,
            $start instanceof CarbonInterface ? $start : Carbon::parse($start),
            $end instanceof CarbonInterface ? $end : Carbon::parse($end)
        );
    }
}
