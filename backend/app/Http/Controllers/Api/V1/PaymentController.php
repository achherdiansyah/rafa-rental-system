<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payment\SubmitPaymentAction;
use App\Actions\Payment\VerifyPaymentAction;
use App\Enums\PaymentStatus;
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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends ApiController
{
    /**
     * Admin payment queue: payments across invoices (default SUBMITTED).
     */
    public function queue(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('manage', Payment::class);

        $query = Payment::with([
            'attachments',
            'invoice.booking.projectLocation',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        } else {
            $query->where('status', PaymentStatus::SUBMITTED);
        }

        $paginated = $query->paginate(min(max((int) $request->query('per_page', 10), 1), 50));

        return $this->success(
            PaymentResource::collection($paginated->items()),
            'Antrean pembayaran berhasil dimuat.',
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
     * Stream an uploaded payment proof (private storage) to an admin.
     */
    public function proof(Request $request, Payment $payment): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $payment);

        $proof = $payment->attachments()->where('document_type', 'PAYMENT_PROOF')->first();
        if (! $proof || ! Storage::disk('local')->exists($proof->file_path)) {
            abort(404);
        }

        $content = Storage::disk('local')->get($proof->file_path);

        return response($content, 200, [
            'Content-Type' => $proof->mime_type,
            'Content-Disposition' => 'inline; filename="'.$proof->file_name.'"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

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
