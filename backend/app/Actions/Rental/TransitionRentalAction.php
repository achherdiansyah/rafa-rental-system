<?php

namespace App\Actions\Rental;

use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\EquipmentUnit;
use App\Models\Rental;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Executes a rental lifecycle transition and keeps physical unit status in sync.
 * Allowed map (from => [to]):
 *   ASSIGNED -> DISPATCHED
 *   DISPATCHED -> ARRIVED
 *   ARRIVED -> ONGOING          (BAST check-in: started_at set)
 *   ONGOING -> DEMOBILIZING
 *   DEMOBILIZING -> RETURN_INSPECTED
 *   RETURN_INSPECTED -> COMPLETED (BAST check-out: completed_at set)
 */
class TransitionRentalAction
{
    protected const ALLOWED = [
        RentalStatus::ASSIGNED->value => [RentalStatus::DISPATCHED->value],
        RentalStatus::DISPATCHED->value => [RentalStatus::ARRIVED->value],
        RentalStatus::ARRIVED->value => [RentalStatus::ONGOING->value],
        RentalStatus::ONGOING->value => [RentalStatus::DEMOBILIZING->value],
        RentalStatus::DEMOBILIZING->value => [RentalStatus::RETURN_INSPECTED->value],
        RentalStatus::RETURN_INSPECTED->value => [RentalStatus::COMPLETED->value],
    ];

    public function execute(User $admin, Rental $rental, string $targetStatus): Rental
    {
        return DB::transaction(function () use ($admin, $rental, $targetStatus) {
            $target = RentalStatus::from($targetStatus);
            $from = $rental->status->value;

            if (! isset(self::ALLOWED[$from]) || ! in_array($targetStatus, self::ALLOWED[$from], true)) {
                throw new InvalidStateTransitionException(
                    "Transisi rental tidak valid: {$from} -> {$targetStatus}."
                );
            }

            $updates = ['status' => $target];

            if ($target === RentalStatus::ONGOING) {
                $updates['started_at'] = now();
            }

            if ($target === RentalStatus::COMPLETED) {
                $updates['completed_at'] = now();
            }

            $rental->update($updates);

            // Keep physical unit statuses in sync with rental lifecyle
            $rental->loadMissing('details.assignment.unit');
            foreach ($rental->details as $detail) {
                /** @var EquipmentUnit|null $unit */
                $unit = $detail->assignment?->unit;
                if (! $unit) {
                    continue;
                }

                $unitStatus = match ($target) {
                    RentalStatus::DISPATCHED => EquipmentStatus::MOBILIZING,
                    RentalStatus::ARRIVED, RentalStatus::ONGOING => EquipmentStatus::ON_SITE,
                    RentalStatus::DEMOBILIZING => EquipmentStatus::DEMOBILIZING,
                    RentalStatus::RETURN_INSPECTED => EquipmentStatus::RETURN_INSPECTION,
                    RentalStatus::COMPLETED => EquipmentStatus::AVAILABLE,
                    default => $unit->status,
                };

                if ($unitStatus) {
                    $unit->update(['status' => $unitStatus]);
                }
            }

            if ($target === RentalStatus::COMPLETED) {
                $rental->details()->update(['status' => RentalStatus::COMPLETED]);
            }

            AuditLogger::log('RENTAL_'.$targetStatus, $rental, [
                'old_status' => $from,
            ], [
                'new_status' => $targetStatus,
                'processed_by' => $admin->id,
            ]);

            return $rental->fresh()->load([
                'booking.projectLocation',
                'details.assignment.unit.model',
                'details.assignment.unit.model',
            ]);
        });
    }
}
