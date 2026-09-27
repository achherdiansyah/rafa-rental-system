<?php

namespace App\Actions\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentUnit;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssignBookingUnitsAction
{
    public function __construct(
        protected EquipmentAvailabilityService $availabilityService
    ) {}

    /**
     * Assign physical units to a booking (admin-only, atomic, conflict-safe).
     *
     * @param  array<int, array{booking_detail_id: int, equipment_unit_id: int}>  $assignments
     *
     * @throws BusinessRuleException|InvalidStateTransitionException
     */
    public function execute(User $admin, Booking $booking, array $assignments): Booking
    {
        return DB::transaction(function () use ($admin, $booking, $assignments) {
            if ($booking->status !== BookingStatus::APPROVED) {
                throw new InvalidStateTransitionException(
                    'Penugasan unit hanya diperbolehkan pada booking berstatus APPROVED.'
                );
            }

            // Group instructions per detail
            /** @var array<int, int[]> $unitIdsByDetail */
            $unitIdsByDetail = [];
            foreach ($assignments as $assignment) {
                if (empty($assignment['booking_detail_id']) || empty($assignment['equipment_unit_id'])) {
                    throw new BusinessRuleException('Instruksi assignment tidak lengkap.');
                }
                $unitIdsByDetail[(int) $assignment['booking_detail_id']][] = (int) $assignment['equipment_unit_id'];
            }

            $booking->loadMissing('details');

            foreach ($unitIdsByDetail as $detailId => $unitIds) {
                /** @var BookingDetail|null $detail */
                $detail = $booking->details->firstWhere('id', $detailId);

                if (! $detail) {
                    throw new BusinessRuleException("Detail booking #{$detailId} bukan bagian dari booking ini.");
                }

                // Quantity guard: exactly `quantity` units per detail
                if (count($unitIds) !== (int) $detail->quantity) {
                    throw new BusinessRuleException(
                        "Detail #{$detailId} membutuhkan {$detail->quantity} unit, tetapi menerima ".count($unitIds).' unit.'
                    );
                }

                // Pessimistically lock the exact unit rows to prevent concurrent
                // double-booking of the same physical unit.
                /** @var Collection<int, EquipmentUnit> $lockedUnits */
                $lockedUnits = EquipmentUnit::query()
                    ->whereIn('id', $unitIds)
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                foreach ($lockedUnits as $unit) {
                    // Model match validation
                    if ((int) $unit->equipment_model_id !== (int) $detail->equipment_model_id) {
                        throw new BusinessRuleException(
                            "Unit #{$unit->id} bukan milik model pada detail #{$detail->id}."
                        );
                    }

                    // Availability window validation (buffer-aware, no conflict)
                    if (! $this->availabilityService->isUnitAvailable($unit, $detail->start_date, $detail->end_date)) {
                        throw new BusinessRuleException(
                            "Unit #{$unit->id} tidak tersedia pada periode pemesanan (bentrok jadwal atau status non-operasional)."
                        );
                    }

                    if ($unit->status !== EquipmentStatus::AVAILABLE) {
                        throw new BusinessRuleException(
                            "Unit #{$unit->id} tidak berstatus AVAILABLE."
                        );
                    }
                }

                // Create assignment records + move units to ASSIGNED
                foreach ($lockedUnits as $unit) {
                    BookingUnitAssignment::create([
                        'booking_detail_id' => $detail->id,
                        'equipment_unit_id' => $unit->id,
                        'status' => AssignmentStatus::ASSIGNED,
                        'is_current' => true,
                        'assigned_by' => $admin->id,
                    ]);

                    $unit->update(['status' => EquipmentStatus::ASSIGNED]);
                }
            }

            AuditLogger::log('UNITS_ASSIGNED', $booking, [], [
                'assignment_count' => count($assignments),
                'assigned_by' => $admin->id,
            ]);

            $booking->load([
                'projectLocation',
                'details.model' => function ($q) {
                    $q->with(['type', 'prices', 'attachments']);
                },
                'details.unitAssignments.unit',
                'unitAssignments.unit',
            ]);

            return $booking;
        });
    }
}
