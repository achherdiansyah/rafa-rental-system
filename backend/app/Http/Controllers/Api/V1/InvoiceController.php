<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoice\CreateInvoiceAction;
use App\Actions\Invoice\IssueInvoiceAction;
use App\Actions\Invoice\MarkUnpaidInvoiceAction;
use App\Actions\Invoice\VoidInvoiceAction;
use App\Enums\InvoiceType;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoicePdfGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Invoice::with(['booking.projectLocation', 'details'])->latest();

        if (! $user->isAdmin() && ! $user->isOwner()) {
            $query->whereHas('booking', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper((string) $request->query('status')));
        }

        if ($request->filled('booking_id')) {
            $query->where('booking_id', (int) $request->query('booking_id'));
        }

        $paginated = $query->paginate(min(max((int) $request->query('per_page', 10), 1), 50));

        return $this->success(
            InvoiceResource::collection($paginated->items()),
            'Daftar invoice berhasil dimuat.',
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    public function store(CreateInvoiceRequest $request, CreateInvoiceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Booking $booking */
        $booking = Booking::findOrFail($request->validated('booking_id'));

        $invoice = $action->execute($user, $booking, InvoiceType::from($request->validated('invoice_type')));

        return $this->created(
            new InvoiceResource($invoice),
            'Invoice berhasil dibuat (DRAFT).'
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['booking.projectLocation', 'details']);

        return $this->success(
            new InvoiceResource($invoice),
            'Detail invoice berhasil dimuat.'
        );
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoiceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $issued = $action->execute($user, $invoice);

        return $this->success(
            new InvoiceResource($issued),
            'Invoice diterbitkan; jatuh tempo 24 jam sejak penerbitan.'
        );
    }

    public function markUnpaid(Request $request, Invoice $invoice, MarkUnpaidInvoiceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $action->execute($user, $invoice);

        return $this->success(
            new InvoiceResource($updated),
            'Invoice berstatus UNPAID.'
        );
    }

    public function void(Request $request, Invoice $invoice, VoidInvoiceAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cancelled = $action->execute($user, $invoice);

        return $this->success(
            new InvoiceResource($cancelled),
            'Invoice dibatalkan (riwayat keuangan tetap tersimpan).'
        );
    }

    public function pdf(Request $request, Invoice $invoice, InvoicePdfGenerator $generator): Response
    {
        Gate::authorize('view', $invoice);

        $payload = $generator->render($invoice);

        return response($payload, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->invoice_number.'.pdf"',
            'Content-Length' => (string) strlen($payload),
        ]);
    }
}
