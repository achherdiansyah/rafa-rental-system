<?php

namespace Tests\Feature\Reports;

use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Reports\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private function invoiceFor(User $user, string $status, float $grand, array $payments = []): Invoice
    {
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => $status,
            'grand_total' => $grand,
            'overpayment_amount' => $status === 'OVERPAID' ? 200000 : 0,
        ]);
        foreach ($payments as [$amount, $statusOfPayment]) {
            Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $amount, 'status' => $statusOfPayment]);
        }

        return $invoice;
    }

    public function test_invoice_report_uses_approved_payment_basis(): void
    {
        $user = User::factory()->create();
        $this->invoiceFor($user, 'PARTIALLY_PAID', 1000000, [[600000, PaymentStatus::APPROVED], [50000, PaymentStatus::REJECTED]]);

        $page = app(FinancialReportService::class)->invoiceReport();

        $this->assertEquals(1, $page->total());
        $row = $page->items()[0];
        $this->assertEqualsWithDelta(600000.0, $row['paid_total'], 0.01, 'REJECTED tidak boleh masuk paid basis.');
        $this->assertEqualsWithDelta(400000.0, $row['balance'], 0.01);
        $this->assertEquals(1000000.0, $row['grand_total']);
    }

    public function test_partial_and_outstanding_reports(): void
    {
        $user = User::factory()->create();
        $this->invoiceFor($user, 'PARTIALLY_PAID', 800000, [[300000, PaymentStatus::APPROVED], [150000, PaymentStatus::APPROVED]]);
        $this->invoiceFor($user, 'OVERDUE', 200000);
        $this->invoiceFor($user, 'PAID', 100000, [[100000, PaymentStatus::APPROVED]]);

        $service = app(FinancialReportService::class);

        $partials = $service->partialReport();
        $this->assertEquals(1, $partials->total());
        $pRow = $partials->items()[0];
        $this->assertEquals(2, $pRow['approved_count']);
        $this->assertEqualsWithDelta(450000.0, $pRow['paid_total'], 0.01);
        $this->assertNotNull($pRow['last_payment_at']);

        $outstanding = $service->outstandingReport();
        $this->assertEquals(2, $outstanding->total(), 'PAID tidak masuk outstanding.');
        $this->assertTrue(collect($outstanding->items())->contains(fn ($r) => $r['overdue_flag'] === true));
    }

    public function test_overpayment_and_refund_reports(): void
    {
        $user = User::factory()->create();
        $invoice = $this->invoiceFor($user, 'OVERPAID', 800000, [[1000000, PaymentStatus::APPROVED]]);
        $refund = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'status' => RefundStatus::COMPLETED,
            'amount' => 200000,
        ]);

        $service = app(FinancialReportService::class);

        $overpay = $service->overpaymentReport();
        $this->assertEquals(1, $overpay->total());
        $row = $overpay->items()[0];
        $this->assertEqualsWithDelta(200000.0, $row['overpayment_amount'], 0.01);
        $this->assertEqualsWithDelta(200000.0, $row['refunded_total'], 0.01);
        $this->assertEqualsWithDelta(1000000.0, $row['paid_total'], 0.01); // basis approved payments

        $refunds = $service->refundReport(['source' => RefundSource::OVERPAYMENT->value]);
        $this->assertEquals(1, $refunds->total());
        $this->assertEquals($refund->id, $refunds->items()[0]['id']);
        $this->assertEquals($user->name, $refunds->items()[0]['customer_name']);
    }

    public function test_scoped_user_isolation_and_immutability(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->invoiceFor($user, 'UNPAID', 500000);
        $this->invoiceFor($other, 'UNPAID', 900000);

        $scoped = app(FinancialReportService::class)->invoiceReport([], (int) $user->id);
        $this->assertEquals(1, $scoped->total());
        $this->assertEquals($mine->id, $scoped->items()[0]['id']);
        $this->assertEquals(500000.0, $scoped->items()[0]['grand_total']);

        // Immutable: values untouched by reads
        $this->assertEquals(500000.0, (float) $mine->fresh()->grand_total);
        $this->assertEquals('UNPAID', $mine->fresh()->status->value);
    }

    public function test_api_financial_reports_are_accessible(): void
    {
        $user = User::factory()->create();
        $this->invoiceFor($user, 'UNPAID', 300000);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/reports/financial/invoices')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/reports/financial/outstanding')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/reports/financial/refunds')->assertOk();
    }
}
