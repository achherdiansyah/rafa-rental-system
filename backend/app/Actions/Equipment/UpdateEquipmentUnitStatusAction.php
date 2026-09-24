<?php

namespace App\Actions\Equipment;

use App\Enums\EquipmentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\EquipmentUnit;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateEquipmentUnitStatusAction
{
    /**
     * Manually update the physical unit status safely.
     *
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(EquipmentUnit $unit, string $newStatus, ?string $notes = null): EquipmentUnit
    {
        return DB::transaction(function () use ($unit, $newStatus, $notes) {
            // Lock the row for update
            /** @var EquipmentUnit $unit */
            $unit = EquipmentUnit::where('id', $unit->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $unit->status->value;

            // Normalize enum string
            $newStatusEnum = EquipmentStatus::from($newStatus);

            // Bypass if no change
            if ($oldStatus === $newStatusEnum->value) {
                return $unit;
            }

            // Forbidden manual transition rules
            $activeRentalStates = [
                EquipmentStatus::ASSIGNED->value,
                EquipmentStatus::MOBILIZING->value,
                EquipmentStatus::ON_SITE->value,
                EquipmentStatus::DEMOBILIZING->value,
            ];

            // 1. Cannot manually change status if unit is in active rental flow
            if (in_array($oldStatus, $activeRentalStates, true)) {
                throw new InvalidStateTransitionException(
                    "Unit sedang dalam siklus sewa aktif ($oldStatus). Perubahan status manual tidak diizinkan."
                );
            }

            // 2. Can only go to AVAILABLE from MAINTENANCE or RETURN_INSPECTION
            if ($newStatusEnum === EquipmentStatus::AVAILABLE && ! in_array($oldStatus, [EquipmentStatus::MAINTENANCE->value, EquipmentStatus::RETURN_INSPECTION->value], true)) {
                throw new InvalidStateTransitionException(
                    'Unit hanya dapat menjadi AVAILABLE dari status MAINTENANCE atau RETURN_INSPECTION.'
                );
            }

            // Update status
            $unit->update(['status' => $newStatusEnum]);

            // Log Audit Trail
            AuditLogger::log(
                action: 'UNIT_STATUS_UPDATED',
                entity: $unit,
                oldState: ['status' => $oldStatus],
                newState: ['status' => $newStatusEnum->value],
                metadata: ['notes' => $notes, 'source' => 'manual_admin']
            );

            return $unit;
        });
    }
}
