<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use App\Services\Reports\FinancialReportService;
use App\Services\Reports\OperationalReportService;
use App\Services\Reports\ReportingQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only reporting endpoints. BUSINESS data is never mutated here.
 * Full scope for ADMIN/OWNER; USER receives scoped (own) summaries only.
 */
class ReportingController extends ApiController
{
    public function __construct(
        private readonly ReportingQueryService $service,
        private readonly OperationalReportService $operational,
        private readonly FinancialReportService $financial
    ) {}

    public function bookings(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->bookingSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan booking berhasil dimuat.'
        );
    }

    public function rentals(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->rentalSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan rental berhasil dimuat.'
        );
    }

    public function timesheet(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->timesheetSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan timesheet berhasil dimuat.'
        );
    }

    public function financial(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->financialSummary($this->filters($request), $this->scopeFor($request)),
            'Laporan keuangan berhasil dimuat.'
        );
    }

    /**
     * Equipment utilization requires ADMIN/OWNER (cross-customer asset data).
     */
    /**
     * Single-payload admin/owner dashboard (minimises API requests).
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        return $this->success(
            $this->service->dashboard($this->filters($request)),
            'Dashboard operasional berhasil dimuat.'
        );
    }

    // ------------------------------------------------------------------
    // Row-level operational reports (drillable, paginated, sortable)
    // ------------------------------------------------------------------

    public function operationalBookings(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->operational->bookingReport($this->filters($request), $this->scopeFor($request)),
            'Laporan booking (operasional) berhasil dimuat.'
        );
    }

    public function operationalTimesheets(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->operational->timesheetReport($this->filters($request), $this->scopeFor($request)),
            'Laporan timesheet jam aktual berhasil dimuat.'
        );
    }

    public function operationalRentals(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->operational->rentalUtilization($this->filters($request), $this->scopeFor($request)),
            'Laporan utilasi rental berhasil dimuat.'
        );
    }

    public function operationalActivity(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->operational->projectCustomerActivity($this->filters($request), $this->scopeFor($request)),
            'Laporan aktivitas proyek/pelanggan berhasil dimuat.'
        );
    }

    /**
     * Equipment fleet report is cross-customer (ADMIN/OWNER only).
     */
    public function operationalEquipment(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        return $this->paginated(
            $this->operational->equipmentReport($this->filters($request)),
            'Laporan status armada berhasil dimuat.'
        );
    }

    private function paginated(LengthAwarePaginator $paginated, string $message): JsonResponse
    {
        return $this->success(
            $paginated->items(),
            $message,
            200,
            [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ]
        );
    }

    // ------------------------------------------------------------------
    // Financial row-level reports (approved-payment basis)
    // ------------------------------------------------------------------

    public function financialInvoices(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->financial->invoiceReport($this->filters($request), $this->scopeFor($request)),
            'Laporan invoice berhasil dimuat.'
        );
    }

    public function financialPayments(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->financial->paymentReport($this->filters($request), $this->scopeFor($request)),
            'Laporan pembayaran berhasil dimuat.'
        );
    }

    public function financialPartials(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->financial->partialReport($this->filters($request), $this->scopeFor($request)),
            'Laporan pembayaran sebagian berhasil dimuat.'
        );
    }

    public function financialOutstanding(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->financial->outstandingReport($this->filters($request), $this->scopeFor($request)),
            'Laporan outstanding berhasil dimuat.'
        );
    }

    public function financialOverpayments(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->financial->overpaymentReport($this->filters($request), $this->scopeFor($request)),
            'Laporan kelebihan bayar berhasil dimuat.'
        );
    }

    public function financialRefunds(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->financial->refundReport($this->filters($request), $this->scopeFor($request)),
            'Laporan refund berhasil dimuat.'
        );
    }

    /**
     * Stream the active-filtered report as CSV (Excel-friendly, UTF-8 BOM).
     * Respects the same authorization scope; chunked via pagination (1..N).
     * BUSINESS data is never mutated.
     */
    public function export(Request $request, string $type): StreamedResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $scope = $this->scopeFor($request);

        if ($type === 'equipment' && ! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        $filters = $this->filters($request);
        $filters['per_page'] = 500;

        $listing = $this->listingResolver($type, $filters, $scope, $user);
        if (! $listing) {
            abort(404);
        }

        $filename = $this->exportFilename($type, $filters, $user);

        return response()->streamDownload(function () use ($listing) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            $headerWritten = false;

            for ($page = 1; $page <= 100; $page++) {
                $paginated = $listing($page);
                $items = $paginated->items();
                if (! $headerWritten && count($items) > 0) {
                    fputcsv($handle, array_keys($items[0]));
                    $headerWritten = true;
                }
                foreach ($items as $row) {
                    fputcsv($handle, array_values($row));
                }
                if ($page >= $paginated->lastPage() || count($items) === 0) {
                    break;
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Resolve the row-level listing callback for a report type (filter+scope aware).
     *
     * @param  array<string, mixed>  $filters
     * @return (callable(int): LengthAwarePaginator)|null
     */
    private function listingResolver(string $type, array $filters, ?int $scope, User $user): ?callable
    {
        $byType = [
            'bookings' => fn (int $page) => $this->operational->bookingReport($filters + ['page' => $page], $scope),
            'timesheets' => fn (int $page) => $this->operational->timesheetReport($filters + ['page' => $page], $scope),
            'rentals' => fn (int $page) => $this->operational->rentalUtilization($filters + ['page' => $page], $scope),
            'activity' => fn (int $page) => $this->operational->projectCustomerActivity($filters + ['page' => $page], $scope),
            'invoices' => fn (int $page) => $this->financial->invoiceReport($filters + ['page' => $page], $scope),
            'payments' => fn (int $page) => $this->financial->paymentReport($filters + ['page' => $page], $scope),
            'partials' => fn (int $page) => $this->financial->partialReport($filters + ['page' => $page], $scope),
            'outstanding' => fn (int $page) => $this->financial->outstandingReport($filters + ['page' => $page], $scope),
            'overpayments' => fn (int $page) => $this->financial->overpaymentReport($filters + ['page' => $page], $scope),
            'refunds' => fn (int $page) => $this->financial->refundReport($filters + ['page' => $page], $scope),
        ];

        if ($type === 'equipment') {
            return fn (int $page) => $this->operational->equipmentReport($filters + ['page' => $page]);
        }

        return $byType[$type] ?? null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function exportFilename(string $type, array $filters, User $user): string
    {
        $from = isset($filters['from']) ? preg_replace('/[^0-9-]/', '', (string) $filters['from']) : 'open';
        $to = isset($filters['to']) ? preg_replace('/[^0-9-]/', '', (string) $filters['to']) : 'open';

        return sprintf('rafa-report-%s-%s-to-%s.csv', $type, $from, $to);
    }

    public function equipmentUtilization(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isOwner()) {
            return $this->error('Akses ditolak.', [], 403, 'FORBIDDEN');
        }

        return $this->success(
            $this->service->equipmentUtilization($this->filters($request)),
            'Laporan utilisasi armada berhasil dimuat.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        $allowed = ['from', 'to', 'status', 'source', 'customer_id', 'project_id', 'model_id', 'rental_id', 'unit_id', 'invoice_id'];

        return collect($allowed)->mapWithKeys(fn ($key) => [
            $key => $request->query($key),
        ])->filter(fn ($v) => $v !== null && $v !== '')->all();
    }

    private function scopeFor(Request $request): ?int
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isAdmin() || $user->isOwner() ? null : (int) $user->id;
    }
}
