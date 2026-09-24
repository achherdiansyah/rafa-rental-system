<?php

namespace Tests\Feature\Domain;

use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_belongs_to_booking(): void
    {
        $booking = Booking::factory()->create();
        $invoice = Invoice::factory()->create(['booking_id' => $booking->id]);

        $this->assertTrue($invoice->booking->is($booking));
    }

    public function test_invoice_has_many_details_with_snapshot_pricing(): void
    {
        $invoice = Invoice::factory()->create();
        $detail = InvoiceDetail::factory()->create([
            'invoice_id' => $invoice->id,
            'unit_price' => 2000000.00,
            'quantity' => 2,
            'subtotal' => 4000000.00,
        ]);

        $this->assertCount(1, $invoice->details);
        $this->assertEquals('2000000.00', $detail->unit_price);
        $this->assertEquals('4000000.00', $detail->subtotal);
    }

    public function test_invoice_has_many_payments_supporting_partial_payment(): void
    {
        $invoice = Invoice::factory()->partiallyPaid()->create(['grand_total' => 10000000.00]);
        $bank = BankAccount::factory()->create();

        $payment1 = Payment::factory()->approved()->create([
            'invoice_id' => $invoice->id,
            'bank_account_id' => $bank->id,
            'amount' => 5000000.00,
        ]);

        $payment2 = Payment::factory()->submitted()->create([
            'invoice_id' => $invoice->id,
            'bank_account_id' => $bank->id,
            'amount' => 5000000.00,
        ]);

        $this->assertCount(2, $invoice->payments);
        $this->assertTrue($payment1->invoice->is($invoice));
        $this->assertTrue($payment2->invoice->is($invoice));
        $this->assertEquals('5000000.00', $payment1->amount);
    }

    public function test_payment_belongs_to_one_invoice_strictly(): void
    {
        $payment = Payment::factory()->create();

        $this->assertNotNull($payment->invoice_id);
        $this->assertInstanceOf(Invoice::class, $payment->invoice);
    }

    public function test_invoice_supports_overpayment_state(): void
    {
        $invoice = Invoice::factory()->overpaid()->create([
            'grand_total' => 15000000.00,
            'paid_amount' => 16000000.00,
            'overpayment_amount' => 1000000.00,
        ]);

        $this->assertEquals('16000000.00', $invoice->paid_amount);
        $this->assertEquals('1000000.00', $invoice->overpayment_amount);
    }

    public function test_invoice_has_many_refunds(): void
    {
        $invoice = Invoice::factory()->create();
        $refund = Refund::factory()->completed()->create([
            'invoice_id' => $invoice->id,
            'amount' => 1000000.00,
        ]);

        $this->assertCount(1, $invoice->refunds);
        $this->assertEquals('1000000.00', $refund->amount);
        $this->assertNotNull($refund->processedByUser);
    }

    public function test_financial_records_restrict_deletes(): void
    {
        $invoice = Invoice::factory()->create();
        Payment::factory()->create(['invoice_id' => $invoice->id]);

        $this->expectException(QueryException::class);
        $invoice->forceDelete();
    }
}
