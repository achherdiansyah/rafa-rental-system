<?php

namespace App\Actions\Booking;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentUnit;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReplaceUnitAssignmentAction
{
    public function __construct(
        protected EquipmentAvailabilityService $availabilityService
    ) {}

    /**
     * Replace an assigned unit: retire the old assignment (is_current=false,
     * status REPLACED), send the old unit to MAINTENANCE, and assign the
     * replacement unit (API Contract 1.10).
     *
     * @throws BusinessRuleException|InvalidStateTransitionException
     */
    public function execute(
        User $admin,
        Booking $booking,
        BookingUnitAssignment $oldAssignment,
        int $newUnitId,
        string $reason
    ): Booking {
        return DB::transaction(function () use ($admin, $booking, $oldAssignment, $newUnitId, $reason) {
            if ($booking->status !== BookingStatus::APPROVED) {
                throw new InvalidStateTransitionException(
                    'Penggantian unit hanya diperbolehkan pada booking berstatus APPROVED.'
                );
            }

            // Old assignment must belong to this booking
            if ((int) $oldAssignment->detail->booking_id !== (int) $booking->id || ! $oldAssignment->is_current) {
                throw new BusinessRuleException('Assignment lama tidak valid atau sudah tidak aktif.');
            }

            // Lock replacement unit
            /** @var EquipmentUnit|null $newUnit */
            $newUnit = EquipmentUnit::query()
                ->where('id', $newUnitId)
                ->lockForUpdate()
                ->first();

            if (! $newUnit || $newUnit->status !== EquipmentStatus::AVAILABLE) {
                throw new BusinessRuleException('Unit pengganti tidak berstatus AVAILABLE.');
            }

            $detail = $oldAssignment->detail;

            if ((int) $newUnit->equipment_model_id !== (int) $detail->equipment_model_id) {
                throw new BusinessRuleException('Unit pengganti harus memiliki model yang identik dengan unit lama.');
            }

            if (! $this->availabilityService->isUnitAvailable($newUnit, $detail->start_date, $detail->end_date)) {
                throw new BusinessRuleException('Unit pengganti tidak tersedia pada periode pemesanan.');
            }

            // Retire old assignment (history preserved)
            $oldAssignment->update([
                'is_current' => false,
                'status' => AssignmentStatus::REPLACED,
                'replaced_reason' => $reason,
            ]);

            // Old unit goes to MAINTENANCE (damaged pre-dispatch)
            $oldAssignment->unit()->update(['status' => EquipmentStatus::MAINTENANCE]);

            // Create new assignment
            BookingUnitAssignment::create([
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $newUnit->id,
                'status' => AssignmentStatus::ASSIGNED,
                'is_current' => true,
                'assigned_by' => $admin->id,
            ]);

            $newUnit->update(['status' => EquipmentStatus::ASSIGNED]);

            AuditLogger::log('UNIT_REPLACED', $booking, [
                'old_unit_id' => $oldAssignment->equipment_unit_id,
            ], [
                'new_unit_id' => $newUnit->id,
                'replacement_reason' => $reason,
                'assigned_by' => $admin->id,
            ]);

            $booking->load([
                'projectLocation',
                'details.unitAssignments.unit',
                'unitAssignments.unit',
            ]);

            return $booking;
        });
    }
}
