<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\SubmitPaymentAction;
use App\Actions\Payment\VerifyPaymentAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Payment\RejectPaymentRequest;
use App\Http\Requests\Payment\SubmitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends ApiController
{
    /**
     * List payments for an invoice (owner/manager scope via InvoicePolicy::view).
     */
    public function index(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        $payments = $invoice->payments()
            ->with(['attachments', 'invoice.booking.projectLocation'])
            ->latest()
            ->get();

        return $this->success(
            PaymentResource::collection($payments),
            'Daftar pembayaran invoice berhasil dimuat.'
        );
    }

    /**
     * User submits payment proof -> SUBMITTED (no auto-approval).
     */
    public function store(SubmitPaymentRequest $request, Invoice $invoice, SubmitPaymentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payment = $action->execute($user, $invoice, $request->validated(), $request->file('proof'));

        return $this->created(
            new PaymentResource($payment),
            'Bukti pembayaran diajukan dan menunggu verifikasi Admin.'
        );
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        $payment->load(['attachments', 'invoice.booking.projectLocation']);

        return $this->success(
            new PaymentResource($payment),
            'Detail pembayaran berhasil dimuat.'
        );
    }

    /**
     * Admin approves a SUBMITTED payment (settlement against invoice balance).
     */
    public function approve(Request $request, Payment $payment, VerifyPaymentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->approve($user, $payment);

        return $this->success(
            new PaymentResource($updated),
            'Pembayaran diverifikasi (settlement diterapkan).'
        );
    }

    /**
     * Admin rejects a SUBMITTED payment with a mandatory reason. Row is kept.
     */
    public function reject(RejectPaymentRequest $request, Payment $payment, VerifyPaymentAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->reject($user, $payment, (string) $request->validated('reason'));

        return $this->success(
            new PaymentResource($updated),
            'Pembayaran ditolak; riwayat tetap tersimpan.'
        );
    }
}
