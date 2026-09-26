<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\CreateBookingFromCartAction;
use App\Actions\Booking\SubmitBookingAction;
use App\Actions\Cart\GetOrCreateUserCartAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
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

        /** @var Cart $cart */
        $cart = $getCart->execute($user);
        $cart->load(['projectLocation', 'items']);

        $booking = $action->execute($user, $cart);

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
}
