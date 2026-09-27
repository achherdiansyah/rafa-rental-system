<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Finance\CustomerOutstandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutstandingController extends ApiController
{
    public function __construct(
        private readonly CustomerOutstandingService $service
    ) {}

    /**
     * Customer's own outstanding (self-scoped).
     */
    public function mine(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            $this->service->forCustomer((int) $user->id),
            'Status outstanding akun Anda berhasil dimuat.'
        );
    }

    /**
     * Per-customer outstanding across open invoices (admin/owner).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        $result = $request->filled('user_id')
            ? [$this->service->forCustomer((int) $request->query('user_id'))]
            : $this->service->allCustomers();

        return $this->success(
            $result,
            'Rekap outstanding antar-pelanggan berhasil dimuat.'
        );
    }
}
