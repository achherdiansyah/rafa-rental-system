<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\ReturnBookingAction;
use App\Actions\Booking\ValidateBookingAction;
use App\Enums\BookingStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Booking\ReturnBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupervisorBookingController extends ApiController
{
    /**
     * Validation queue: bookings awaiting Supervisor validation.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with([
            'user:id,name',
            'projectLocation',
            'details.model' => fn ($q) => $q->with(['type', 'prices', 'attachments']),
            'details.unitAssignments.unit',
        ])
            ->where('status', BookingStatus::PENDING_APPROVAL)
            ->latest();

        if ($request->filled('queue') && $request->query('queue') === 'revision') {
            $query->where('supervisor_decision', 'REVISION');
        } elseif (! $request->filled('queue') || $request->query('queue') === 'pending') {
            $query->where(function ($q) {
                $q->whereNull('supervisor_decision')->orWhere('supervisor_decision', 'REVISION');
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->success(
            BookingResource::collection($paginated->items()),
            'Antrean validasi Supervisor berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking->load([
            'user:id,name',
            'projectLocation',
            'details.model' => fn ($q) => $q->with(['type', 'prices', 'attachments']),
            'details.unitAssignments.unit',
        ]);

        return $this->success(new BookingResource($booking), 'Detail booking berhasil dimuat.');
    }

    public function validateBooking(Request $request, Booking $booking, ValidateBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('validate', Booking::class);

        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $updated = $action->execute($user, $booking, $validated['notes'] ?? null);

        return $this->success(new BookingResource($updated), 'Booking lolos validasi dan diteruskan ke Admin.');
    }

    public function returnBooking(ReturnBookingRequest $request, Booking $booking, ReturnBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('validate', Booking::class);

        $updated = $action->execute($user, $booking, $request->validated('notes'));

        return $this->success(new BookingResource($updated), 'Booking dikembalikan untuk perbaikan.');
    }
}