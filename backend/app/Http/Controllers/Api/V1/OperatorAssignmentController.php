<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\RentalOperatorAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OperatorAssignmentController extends ApiController
{
    /**
     * Operator dashboard: counts of active assignments/rentals.
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $active = RentalOperatorAssignment::with(['rental.booking.projectLocation', 'rental.activePaymentHold.triggerInvoice', 'rentalDetail.assignment.unit'])
            ->where('operator_user_id', $user->id)
            ->where('status', RentalOperatorAssignment::ACTIVE)
            ->get();

        return $this->success([
            'active_assignments' => $active->count(),
            'active_rentals' => $active->pluck('rental_id')->unique()->count(),
            'assignments' => $active->map(fn (RentalOperatorAssignment $a) => $this->payload($a))->values(),
        ], 'Dashboard Operator berhasil dimuat.');
    }

    /**
     * List the operator's current (ACTIVE) assignments only. Ended assignments
     * remain in the admin history, but no longer grant project data access.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $assignments = RentalOperatorAssignment::with([
            'rental.booking.projectLocation',
            'rental.activePaymentHold.triggerInvoice',
            'rentalDetail.assignment.unit',
            'assignedByUser:id,name',
        ])
            ->where('operator_user_id', $user->id)
            ->where('status', RentalOperatorAssignment::ACTIVE)
            ->latest()
            ->get();

        return $this->success(
            $assignments->map(fn (RentalOperatorAssignment $a) => $this->payload($a))->values(),
            'Penugasan Operator berhasil dimuat.'
        );
    }

    /**
     * Assignment detail with rental + project location (ownership enforced).
     */
    public function show(Request $request, RentalOperatorAssignment $assignment): JsonResponse
    {
        Gate::authorize('view', $assignment);

        $assignment->load([
            'rental.booking.projectLocation',
            'rental.activePaymentHold.triggerInvoice',
            'rentalDetail.assignment.unit',
            'assignedByUser:id,name',
        ]);

        return $this->success($this->payload($assignment), 'Detail penugasan berhasil dimuat.');
    }

    private function payload(RentalOperatorAssignment $assignment): array
    {
        $rental = $assignment->rental;
        $booking = $rental?->booking;
        $project = $booking?->projectLocation;
        $unit = $assignment->rentalDetail?->assignment?->unit;

        return [
            'id' => $assignment->id,
            'rental_id' => $assignment->rental_id,
            'rental_detail_id' => $assignment->rental_detail_id,
            'rental_status' => $rental?->status?->value ?? $rental?->status,
            'booking_code' => $booking?->booking_code,
            'project_location' => $project ? [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'address' => $project->address,
                'city' => $project->city,
                'latitude' => $project->latitude !== null ? (float) $project->latitude : null,
                'longitude' => $project->longitude !== null ? (float) $project->longitude : null,
                'pic_name' => $project->pic_name,
                'pic_phone' => $project->pic_phone,
            ] : null,
            'unit' => $unit ? [
                'id' => $unit->id,
                'serial_number' => $unit->serial_number,
                'plate_number' => $unit->plate_number,
                'status' => $unit->status?->value ?? $unit->status,
            ] : null,
            'status' => $assignment->status,
            'started_at' => $assignment->started_at?->toIso8601String(),
            'ended_at' => $assignment->ended_at?->toIso8601String(),
            'payment_hold' => $rental?->activePaymentHold ? [
                'id' => $rental->activePaymentHold->id,
                'reason_code' => $rental->activePaymentHold->reason_code,
                'outstanding_amount' => (float) $rental->activePaymentHold->outstanding_amount,
                'started_at' => $rental->activePaymentHold->started_at?->toIso8601String(),
            ] : null,
        ];
    }
}