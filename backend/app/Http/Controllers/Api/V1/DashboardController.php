<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\BookingResource;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\ProjectLocationResource;
use App\Http\Resources\RentalResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Batched dashboard summary for authenticated users.
 * Fetches bookings, rentals, invoices, project locations and notifications in
 * a SINGLE request — avoids N parallel round-trips to a single-threaded server.
 */
class DashboardController extends ApiController
{
    public function userSummary(Request $request, $bookingRepo = null): JsonResponse
    {
        $user = $request->user();

        $bookings = $user->bookings()
            ->with(['projectLocation'])
            ->latest()
            ->limit(5)
            ->get();

        $rentals = $user->rentals()
            ->with(['booking.projectLocation'])
            ->latest()
            ->limit(5)
            ->get();

        $invoices = $user->invoices()
            ->latest()
            ->limit(6)
            ->get();

        $locations = $user->projectLocations()
            ->latest()
            ->limit(6)
            ->get();

        $notifications = $user->notifications()
            ->latest()
            ->limit(6)
            ->get();

        return $this->success([
            'bookings' => BookingResource::collection($bookings),
            'rentals' => RentalResource::collection($rentals),
            'invoices' => InvoiceResource::collection($invoices),
            'project_locations' => ProjectLocationResource::collection($locations),
            'notifications' => NotificationResource::collection($notifications),
        ], 'Ringkasan dashboard berhasil dimuat.');
    }

    public function adminSummary(Request $request): JsonResponse
    {
        return (new ReportingController())->dashboard($request);
    }
}