<?php

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\CustomerOutstandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerOutstandingTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $user, string $status, float $grand, float $paidApproved, float $paidRejected = 0.0, float $overpay = 0.0): Invoice
    {
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'CONFIRMED',
        ]);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'invoice_type' => 'DAILY_WORK',
            'status' => $status,
            'grand_total' => $grand,
            'paid_amount' => $paidApproved,
            'overpayment_amount' => $overpay,
        ]);
        if ($paidApproved > 0) {
            Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $paidApproved, 'status' => PaymentStatus::APPROVED]);
        }
        if ($paidRejected > 0) {
            Payment::factory()->create(['invoice_id' => $invoice->id, 'amount' => $paidRejected, 'status' => PaymentStatus::REJECTED]);
        }

        return $invoice;
    }

    public function test_outstanding_aggregates_open_invoices_only_with_approved_payment_basis(): void
    {
        $user = User::factory()->create();
        // Open: UNPAID (balance 400k), OVERDUE (200k), PARTIAL (0)
        $this->invoice($user, InvoiceStatus::UNPAID->value, 500000, 100000, paidRejected: 50000);
        $this->invoice($user, InvoiceStatus::OVERDUE->value, 200000, 0);
        $this->invoice($user, InvoiceStatus::PARTIALLY_PAID->value, 300000, 300000);
        // Excluded
        $this->invoice($user, InvoiceStatus::PAID->value, 100000, 100000);
        $this->invoice($user, InvoiceStatus::OVERPAID->value, 90000, 90000, overpay: 10000);
        $this->invoice($user, InvoiceStatus::DRAFT->value, 70000, 0);
        $this->invoice($user, InvoiceStatus::CANCELLED->value, 60000, 0);

        $status = app(CustomerOutstandingService::class)->forCustomer((int) $user->id);

        $this->assertEqualsWithDelta(600000.00, $status['total_outstanding'], 0.01);
        $this->assertEquals(3, $status['open_invoice_count']);
        $this->assertEquals(1, $status['overdue_invoice_count']);
        $this->assertFalse($status['eligible']);

        // Approved-payment basis: 50.000 REJECTED excluded from paid
        $unpaid = collect($status['invoices'])->firstWhere('status', 'UNPAID');
        $this->assertEqualsWithDelta(100000.00, $unpaid['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(400000.00, $unpaid['balance_amount'], 0.01);
    }

    public function test_per_customer_isolation_and_eligibility(): void
    {
        $clean = User::factory()->create();
        $this->invoice($clean, InvoiceStatus::PAID->value, 100000, 100000);
        $this->invoice($clean, InvoiceStatus::DRAFT->value, 50000, 0);

        $debtor = User::factory()->create();
        $this->invoice($debtor, InvoiceStatus::UNPAID->value, 250000, 0);

        $service = app(CustomerOutstandingService::class);

        $cleanStatus = $service->forCustomer((int) $clean->id);
        $this->assertEqualsWithDelta(0.0, $cleanStatus['total_outstanding'], 0.01);
        $this->assertEquals(0, $cleanStatus['open_invoice_count']);
        $this->assertTrue($cleanStatus['eligible']);

        $debtorStatus = $service->forCustomer((int) $debtor->id);
        $this->assertEqualsWithDelta(250000.00, $debtorStatus['total_outstanding'], 0.01);
        $this->assertFalse($debtorStatus['eligible']);

        $summary = $service->allCustomers();
        $this->assertCount(1, $summary, 'Hanya customer dengan tagihan terbuka muncul di rekap.');
        $this->assertEquals($debtor->id, $summary[0]['user_id']);
    }

    public function test_outstanding_is_invoice_based_independent_of_booking_status(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => 'DISPATCHED', // rental in operation
        ]);
        Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::UNPAID->value,
            'grand_total' => 400000,
        ]);

        $status = app(CustomerOutstandingService::class)->forCustomer((int) $user->id);

        $this->assertEqualsWithDelta(400000.00, $status['total_outstanding'], 0.01);
        $this->assertEquals('DISPATCHED', $booking->fresh()->status->value, 'Status booking tidak disentuh.');
    }

    public function test_no_n1_queries_on_batch_load(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->invoice($user, InvoiceStatus::UNPAID->value, 100000 + $i, 10000);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        app(CustomerOutstandingService::class)->forCustomer((int) $user->id);

        $this->assertLessThan(8, $queries, 'Eager-loaded batch harus menghindari N+1.');
        $this->assertGreaterThan(0, $queries);
    }

    public function test_api_endpoints_scope_and_authorize(): void
    {
        $user = User::factory()->create();
        $this->invoice($user, InvoiceStatus::UNPAID->value, 300000, 0);
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/finance/outstanding/me')
            ->assertOk()
            ->assertJsonPath('data.total_outstanding', 300000)
            ->assertJsonPath('data.eligible', false);

        // User cannot access admin rekap
        $this->getJson('/api/v1/finance/outstanding')->assertStatus(403);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/finance/outstanding?user_id='.$user->id)
            ->assertOk()
            ->assertJsonPath('data.0.user_id', $user->id);
    }
}
