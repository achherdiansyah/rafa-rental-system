<?php

namespace App\Actions\Rental;

use App\Enums\BookingStatus;
use App\Enums\RentalStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\BookingUnitAssignment;
use App\Models\Rental;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateRentalFromBookingAction
{
    /**
     * Create a Rental (header + detail lines) from a CONFIRMED booking that has
     * physical units already assigned. Each rental_detail references a current
     * booking_unit_assignment (the physical-unit provenance rule).
     *
     * @throws BusinessRuleException
     */
    public function execute(User $admin, Booking $booking): Rental
    {
        return DB::transaction(function () use ($admin, $booking) {
            if ($booking->status !== BookingStatus::CONFIRMED) {
                throw new BusinessRuleException(
                    'Rental hanya dapat dibuat dari booking berstatus CONFIRMED.'
                );
            }

            if ($booking->rental()->exists()) {
                throw new BusinessRuleException('Rental untuk booking ini sudah dibuat.');
            }

            /** @var Collection<int, BookingUnitAssignment> $currentAssignments */
            $currentAssignments = $booking->unitAssignments()
                ->where('is_current', true)
                ->with('unit')
                ->get();

            if ($currentAssignments->isEmpty()) {
                throw new BusinessRuleException(
                    'Unit fisik belum dialokasikan. Admin harus menugaskan unit terlebih dahulu.'
                );
            }

            $rental = Rental::create([
                'booking_id' => $booking->id,
                'status' => RentalStatus::ASSIGNED,
            ]);

            foreach ($currentAssignments as $assignment) {
                if (! $assignment->unit) {
                    throw new BusinessRuleException(
                        'Unit fisik pada assignment tidak ditemukan.'
                    );
                }

                $rental->details()->create([
                    'assignment_id' => $assignment->id,
                    'status' => RentalStatus::ASSIGNED,
                ]);
            }

            AuditLogger::log('RENTAL_CREATED', $rental, [], [
                'booking_code' => $booking->booking_code,
                'detail_count' => $currentAssignments->count(),
                'created_by' => $admin->id,
            ]);

            $rental->load([
                'booking.projectLocation',
                'details.assignment.unit.model',
                'details.assignment.unit.model',
            ]);

            return $rental;
        });
    }
}
