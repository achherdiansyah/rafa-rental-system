<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Timesheet\CreateTimesheetAction;
use App\Actions\Timesheet\SubmitTimesheetAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Timesheet\StoreTimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TimesheetController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Timesheet::with([
            'rentalDetail.assignment.unit',
            'rentalDetail.rental.booking.projectLocation',
        ])->latest('report_date');

        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->whereHas(
                'rentalDetail.rental.booking',
                fn ($q) => $q->where('user_id', $user->id)
            );
        }

        if ($request->filled('rental_detail_id')) {
            $query->where('rental_detail_id', $request->query('rental_detail_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 50);
        $paginated = $query->paginate($perPage);

        return $this->success(
            TimesheetResource::collection($paginated->items()),
            'Daftar timesheet berhasil dimuat.',
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
     * Operator fills a daily timesheet entry (server-side hour calculation).
     */
    public function store(StoreTimesheetRequest $request, CreateTimesheetAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('create', Timesheet::class);

        $timesheet = $action->execute($user, $request->validated());

        return $this->created(
            new TimesheetResource($timesheet),
            'Timesheet harian berhasil dicatat.'
        );
    }

    public function show(Request $request, Timesheet $timesheet): JsonResponse
    {
        Gate::authorize('view', $timesheet);

        $timesheet->load([
            'rentalDetail.assignment.unit',
            'rentalDetail.rental.booking.projectLocation',
        ]);

        return $this->success(
            new TimesheetResource($timesheet),
            'Detail timesheet berhasil dimuat.'
        );
    }

    /**
     * Submit a DRAFT timesheet for Admin validation.
     */
    public function submit(Request $request, Timesheet $timesheet, SubmitTimesheetAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('submit', $timesheet);

        $updated = $action->execute($user, $timesheet);

        return $this->success(
            new TimesheetResource($updated),
            'Timesheet berhasil disubmit untuk validasi Admin.'
        );
    }
}
