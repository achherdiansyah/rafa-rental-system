<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Rental;
use App\Models\RentalPaymentHold;
use App\Services\Billing\PaymentHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentHoldController extends ApiController
{
    public function index(): JsonResponse
    {
        $holds = RentalPaymentHold::with([
            'rental.booking.projectLocation',
            'triggerInvoice',
            'createdByUser:id,name',
            'resolvedByUser:id,name',
        ])->latest()->get()->map(fn (RentalPaymentHold $hold) => $this->payload($hold));

        return $this->success($holds, 'Riwayat PAYMENT HOLD berhasil dimuat.');
    }

    public function resume(Request $request, Rental $rental, PaymentHoldService $service): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $hold = $service->resume($request->user(), $rental, $validated['reason']);

        return $this->success($this->payload($hold->load('rental')), 'PAYMENT HOLD berhasil di-resume.');
    }

    private function payload(RentalPaymentHold $hold): array
    {
        $rental = $hold->rental;

        return [
            'id' => $hold->id,
            'rental_id' => $hold->rental_id,
            'booking_code' => $rental?->booking?->booking_code,
            'project_name' => $rental?->booking?->projectLocation?->project_name,
            'status' => $hold->status,
            'reason_code' => $hold->reason_code,
            'trigger_invoice_id' => $hold->trigger_invoice_id,
            'trigger_invoice_number' => $hold->triggerInvoice?->invoice_number,
            'outstanding_amount' => (float) $hold->outstanding_amount,
            'started_at' => $hold->started_at?->toIso8601String(),
            'resolved_at' => $hold->resolved_at?->toIso8601String(),
            'resolution_reason' => $hold->resolution_reason,
            'created_by_name' => $hold->createdByUser?->name,
            'resolved_by_name' => $hold->resolvedByUser?->name,
        ];
    }
}
