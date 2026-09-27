<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Rental\CreateRentalFromBookingAction;
use App\Actions\Rental\InspectRentalAction;
use App\Actions\Rental\TransitionRentalAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Rental\CreateRentalRequest;
use App\Http\Requests\Rental\ReadyRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\Booking;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RentalController extends ApiController
{
    public const TRANSITION_MAP = [
        'dispatch' => 'DISPATCHED',
        'arrive' => 'ARRIVED',
        'start' => 'ONGOING',
        'return' => 'DEMOBILIZING',
        'inspect' => 'RETURN_INSPECTED',
    ];

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Rental::with([
            'booking.projectLocation',
            'details.assignment.unit.model',
        ])->latest();

        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->whereHas('booking', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($request->filled('booking_id')) {
            $query->where('booking_id', $request->query('booking_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->success(
            RentalResource::collection($paginated->items()),
            'Daftar rental berhasil dimuat.',
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
     * Create a Rental from a CONFIRMED booking with assigned units (admin).
     */
    public function store(CreateRentalRequest $request, CreateRentalFromBookingAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('operate', Rental::class);

        /** @var Booking $booking */
        $booking = Booking::findOrFail($request->validated('booking_id'));

        $rental = $action->execute($user, $booking);

        return $this->created(
            new RentalResource($rental),
            'Rental berhasil dibuat dari booking.'
        );
    }

    public function show(Request $request, Rental $rental): JsonResponse
    {
        Gate::authorize('view', $rental);

        $rental->load([
            'booking.projectLocation',
            'details.assignment.unit.model',
        ]);

        return $this->success(
            new RentalResource($rental),
            'Detail rental berhasil dimuat.'
        );
    }

    /**
     * Forward a rental to the next lifecycle state (admin).
     */
    public function transition(Request $request, Rental $rental, string $target, TransitionRentalAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('operate', Rental::class);

        $targetStatus = self::TRANSITION_MAP[$target] ?? null;
        if (! $targetStatus) {
            return $this->error(
                'Transisi tidak dikenal.',
                [],
                400,
                'VALIDATION_ERROR'
            );
        }

        $updated = $action->execute($user, $rental, $targetStatus);

        return $this->success(
            new RentalResource($updated),
            "Rental berhasil transisi ke {$targetStatus}."
        );
    }

    /**
     * Admin inspection result: mark returned units READY, MAINTENANCE or DAMAGED.
     * Completes the rental; only READY releases units back to AVAILABLE.
     */
    public function ready(ReadyRentalRequest $request, Rental $rental, InspectRentalAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('operate', Rental::class);

        $inspected = $action->execute(
            $user,
            $rental,
            (string) $request->validated('result'),
            $request->validated('condition_notes')
        );

        $message = match ($request->validated('result')) {
            'READY' => 'Inspeksi selesai: unit dinyatakan siap pakai kembali.',
            'MAINTENANCE' => 'Inspeksi selesai: unit masuk maintenance.',
            default => 'Inspeksi selesai: unit tercatat damaged (tanpa tagihan otomatis).',
        };

        return $this->success(
            new RentalResource($inspected),
            $message
        );
    }
}
