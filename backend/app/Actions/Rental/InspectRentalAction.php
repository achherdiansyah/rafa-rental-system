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
 * Finalises a returned rental after admin inspection.
 *
 * Allowed only from RETURN_INSPECTED. Unit readiness is a business decision:
 * - READY       -> unit AVAILABLE (rental COMPLETED)
 * - MAINTENANCE -> unit MAINTENANCE (needs repair before reuse)
 * - DAMAGED     -> unit MAINTENANCE (damage recorded; NO automatic charge / billing)
 *
 * ponytail: damage cost is intentionally NOT converted into a charge here.
 * Add explicit billing (replace/penalty invoice) when PRD requests it.
 */
class InspectRentalAction
{
    public const RESULTS = ['READY', 'MAINTENANCE', 'DAMAGED'];

    public function execute(User $inspector, Rental $rental, string $result, ?string $conditionNotes = null): Rental
    {
        if (! in_array($result, self::RESULTS, true)) {
            throw new InvalidStateTransitionException("Hasil inspeksi tidak valid: {$result}.");
        }

        return DB::transaction(function () use ($inspector, $rental, $result, $conditionNotes) {
            if ($rental->status !== RentalStatus::RETURN_INSPECTED) {
                throw new InvalidStateTransitionException(
                    'Inspeksi hanya dapat dilakukan pada rental berstatus RETURN_INSPECTED.'
                );
            }

            $rental->loadMissing('details.assignment.unit');

            $ready = $result === 'READY';
            foreach ($rental->details as $detail) {
                $detail->update([
                    'inspection_result' => $result,
                    'condition_notes' => $conditionNotes,
                    'checked_out_at' => now(),
                ]);

                /** @var EquipmentUnit|null $unit */
                $unit = $detail->assignment?->unit;
                if ($unit) {
                    $unit->update(['status' => $ready
                        ? EquipmentStatus::AVAILABLE
                        : EquipmentStatus::MAINTENANCE]);
                }
            }

            $rental->update([
                'status' => RentalStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            AuditLogger::log('RENTAL_INSPECTION', $rental, [
                'old_status' => RentalStatus::RETURN_INSPECTED->value,
            ], [
                'new_status' => RentalStatus::COMPLETED->value,
                'inspection_result' => $result,
                'condition_notes' => $conditionNotes,
                'processed_by' => $inspector->id,
            ]);

            return $rental->fresh()->load([
                'booking.projectLocation',
                'details.assignment.unit.model',
            ]);
        });
    }
}
