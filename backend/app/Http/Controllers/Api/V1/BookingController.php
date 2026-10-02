<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\ApproveBookingAction;
use App\Actions\Booking\AssignBookingUnitsAction;
use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingFromCartAction;
use App\Actions\Booking\ExtendPaymentDeadlineAction;
use App\Actions\Booking\RejectBookingAction;
use App\Actions\Booking\ReplaceUnitAssignmentAction;
use App\Actions\Booking\RescheduleBookingAction;
use App\Actions\Booking\SubmitBookingAction;
use App\Actions\Cart\GetOrCreateUserCartAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Booking\AssignBookingUnitsRequest;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\ExtendDeadlineRequest;
use App\Http\Requests\Booking\RejectBookingRequest;
use App\Http\Requests\Booking\ReplaceUnitRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\BookingUnitAssignment;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BookingController extends ApiController
{
    /**
     * Create a booking DRAFT from the user's active cart.
     */
    public function store(
        Request $request,
        GetOrCreateUserCartAction $getCart,
        CreateBookingFromCartAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', Booking::class);

        $selectedItemIds = $request->input('selected_item_ids');
        if ($selectedItemIds !== null && !is_array($selectedItemIds)) {
            return $this->error('Format item terpilih tidak valid', 422);
        }

        /** @var Cart $cart */
        $cart = $getCart->execute($user);
        $cart->load(['projectLocation', 'items']);

        $booking = $action->execute($user, $cart, $selectedItemIds);

        return $this->created(
            new BookingResource($booking),
            'Booking berhasil dibuat sebagai draft dari keranjang sewa.'
        );
    }

    /**
     * List bookings for the authenticated user (scoped by ownership).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Booking::with([
            'projectLocation',
            'details.model' => function ($q) {
                $q->with(['type', 'prices', 'attachments']);
            },
            'details.unitAssignments.unit',
        ])->latest();

        // Ownership scoping: regular users only see their own bookings
        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        // Optional status filter
        if ($request->filled('status')) {
            $status = strtoupper((string) $request->query('status'));
            $query->where('status', $status);
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->success(
            BookingResource::collection($paginated->items()),
            'Daftar booking berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    /**
     * Show booking detail with its line items and project location.
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $booking->load([
            'projectLocation',
            'details.model' => function ($q) {
                $q->with(['type', 'prices', 'attachments']);
            },
            'details.unitAssignments.unit',
        ]);

        return $this->success(
            new BookingResource($booking),
            'Detail booking berhasil dimuat.'
        );
    }

    /**
     * Submit a DRAFT booking into PENDING_APPROVAL.
     */
    public function submit(Request $request, Booking $booking, SubmitBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('submit', $booking);

        $submitted = $action->execute($user, $booking);

        return $this->success(
            new BookingResource($submitted),
            'Booking berhasil disubmit dan menunggu persetujuan.'
        );
    }

    /**
     * Approve a PENDING_APPROVAL booking (admin/owner).
     */
    public function approve(Request $request, Booking $booking, ApproveBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('approve', Booking::class);

        $approved = $action->execute($user, $booking);

        return $this->success(
            new BookingResource($approved),
            'Booking berhasil disetujui.'
        );
    }

    /**
     * Reject a PENDING_APPROVAL booking with reason (admin/owner).
     */
    public function reject(RejectBookingRequest $request, Booking $booking, RejectBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('reject', Booking::class);

        $rejected = $action->execute($user, $booking, $request->validated('rejection_reason'));

        return $this->success(
            new BookingResource($rejected),
            'Booking berhasil ditolak.'
        );
    }

    /**
     * Assign physical units to booking details (admin-exclusive).
     */
    public function assignUnits(
        AssignBookingUnitsRequest $request,
        Booking $booking,
        AssignBookingUnitsAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('assignUnits', Booking::class);

        $updated = $action->execute($user, $booking, $request->validated('assignments'));

        return $this->success(
            new BookingResource($updated),
            'Unit fisik berhasil ditugaskan ke booking.'
        );
    }

    /**
     * Replace a unit assignment with an AVAILABLE unit of the same model (admin).
     */
    public function replaceUnit(
        ReplaceUnitRequest $request,
        Booking $booking,
        BookingUnitAssignment $assignment,
        ReplaceUnitAssignmentAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('replaceUnit', Booking::class);

        $updated = $action->execute(
            $user,
            $booking,
            $assignment,
            (int) $request->validated('new_equipment_unit_id'),
            $request->validated('reason')
        );

        return $this->success(
            new BookingResource($updated),
            'Unit fisik berhasil diganti.'
        );
    }

    /**
     * Manually extend the payment deadline (admin/owner, audited).
     */
    public function extendDeadline(
        ExtendDeadlineRequest $request,
        Booking $booking,
        ExtendPaymentDeadlineAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('extendDeadline', Booking::class);

        $updated = $action->execute(
            $user,
            $booking,
            (int) $request->validated('additional_hours'),
            $request->validated('reason')
        );

        return $this->success(
            new BookingResource($updated),
            'Tenggat pembayaran berhasil diperpanjang.'
        );
    }

    /**
     * Cancel booking with business-rule guarding (USER pre-payment / ADMIN post-payment).
     */
    public function cancel(CancelBookingRequest $request, Booking $booking, CancelBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cancelled = $action->execute($user, $booking, $request->validated('reason'));

        return $this->success(
            new BookingResource($cancelled),
            'Booking berhasil dibatalkan.'
        );
    }

    /**
     * Request reschedule of booking dates (availability re-checked, admin approval required).
     */
    public function reschedule(
        RescheduleBookingRequest $request,
        Booking $booking,
        RescheduleBookingAction $action
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('reschedule', $booking);

        $updated = $action->execute(
            $user,
            $booking,
            $request->validated('new_start_date'),
            $request->validated('new_end_date'),
            $request->validated('reason')
        );

        return $this->success(
            new BookingResource($updated),
            'Permintaan reschedule diajukan dan menunggu persetujuan Admin.'
        );
    }
}
