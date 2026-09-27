<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\NotificationResource;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    /**
     * Own in-app notifications (notifiable is always the authenticated user).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->notifications();

        if ($request->has('read')) {
            $read = filter_var($request->query('read'), FILTER_VALIDATE_BOOL);
            $read ? $query->whereNotNull('read_at') : $query->whereNull('read_at');
        }

        $paginated = $query->latest()->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        return $this->success(
            NotificationResource::collection($paginated->items()),
            'Daftar notifikasi berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            ['count' => $user->unreadNotifications()->count()],
            'Jumlah notifikasi belum dibaca.'
        );
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $row = $user->notifications()->where('id', $notification)->first();
        if (! $row) {
            return $this->error('Notifikasi tidak ditemukan.', [], 404, 'RESOURCE_NOT_FOUND');
        }

        if ($row->read_at === null) {
            $row->update(['read_at' => now()]);
        }

        return $this->success(
            new NotificationResource($row->fresh()),
            'Notifikasi ditandai sudah dibaca.'
        );
    }

    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(
            ['marked' => true],
            'Semua notifikasi ditandai sudah dibaca.'
        );
    }

    /**
     * Admin/Owner: delivery monitor (WhatsApp delivery audit log).
     */
    public function deliveries(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        $paginated = NotificationDelivery::latest()
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 50));

        return $this->success(
            $paginated->items(),
            'Log delivery notifikasi berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }
}
