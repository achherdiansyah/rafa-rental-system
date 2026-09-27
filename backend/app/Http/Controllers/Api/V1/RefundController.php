<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Refund\CompleteRefundRequest;
use App\Http\Requests\Refund\FailRefundRequest;
use App\Http\Requests\Refund\ProcessRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RefundController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Refund::with([
            'invoice.booking.projectLocation',
            'attachments',
        ])->latest();

        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->whereHas('invoice.booking', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        $paginated = $query->paginate(min(max((int) $request->query('per_page', 10), 1), 50));

        return $this->success(
            RefundResource::collection($paginated->items()),
            'Daftar refund berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function show(Request $request, Refund $refund): JsonResponse
    {
        Gate::authorize('view', $refund);

        $refund->load(['invoice.booking.projectLocation', 'attachments']);

        return $this->success(
            new RefundResource($refund),
            'Detail refund berhasil dimuat.'
        );
    }

    public function process(ProcessRefundRequest $request, Refund $refund, RefundLifecycleService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $service->process($user, $refund, $request->validated());

        return $this->success(
            new RefundResource($updated),
            'Refund diproses via transfer bank manual.'
        );
    }

    public function complete(CompleteRefundRequest $request, Refund $refund, RefundLifecycleService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $service->complete($user, $refund, $request->validated(), $request->file('proof'));

        return $this->success(
            new RefundResource($updated),
            'Refund selesai; bukti transfer terlampir.'
        );
    }

    public function fail(FailRefundRequest $request, Refund $refund, RefundLifecycleService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $service->fail($user, $refund, (string) $request->validated('failure_reason'));

        return $this->success(
            new RefundResource($updated),
            'Refund ditandai gagal; riwayat tetap tersimpan.'
        );
    }
}
