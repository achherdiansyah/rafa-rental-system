<?php

namespace Tests\Feature\Reports;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_bookings_csv_respects_filters_and_filename(): void
    {
        $user = User::factory()->create(['name' => 'PT Mitra Sejahtera']);
        $b1 = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value, 'created_at' => '2026-09-05']);
        Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::DRAFT->value, 'created_at' => '2026-09-06']);

        Sanctum::actingAs(User::factory()->admin()->create());
        $res = $this->get('/api/v1/reports/export/bookings?from=2026-09-01&to=2026-09-30&status='.BookingStatus::CONFIRMED->value);

        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));
        $this->assertStringContainsString('rafa-report-bookings-2026-09-01-to-2026-09-30.csv', (string) $res->headers->get('content-disposition'));

        $body = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('booking_code', $body);
        $this->assertStringContainsString((string) $b1->booking_code, $body);
        $this->assertStringNotContainsString('DRAFT', $body, 'Filter status aktif harus membatasi dataset.');
    }

    public function test_export_user_scope_contains_only_own_rows(): void
    {
        $me = User::factory()->create(['name' => 'Customer A']);
        $other = User::factory()->create(['name' => 'Customer B']);
        $mine = Booking::factory()->create(['user_id' => $me->id, 'status' => BookingStatus::CONFIRMED->value]);
        Booking::factory()->create(['user_id' => $other->id, 'status' => BookingStatus::CONFIRMED->value]);

        Sanctum::actingAs($me);
        $res = $this->get('/api/v1/reports/export/bookings');

        $res->assertOk();
        $body = $res->streamedContent();
        $this->assertStringContainsString((string) $mine->booking_code, $body);
        $this->assertStringNotContainsString('Customer B', $body, 'Data customer lain jangan bocor.');
        $this->assertStringNotContainsString('Customer B', $body);
    }

    public function test_export_equipment_forbidden_for_user_and_payments_ok_for_admin(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);
        $this->get('/api/v1/reports/export/equipment')->assertStatus(403);

        // Payments export ok for admin, files contain data only user permitted (own scope)
        $invoiceInvoice = Invoice::factory()->create(['status' => 'ISSUED', 'grand_total' => 100000]);
        Payment::factory()->create(['invoice_id' => $invoiceInvoice->id, 'status' => PaymentStatus::APPROVED, 'amount' => 100000]);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $res = $this->get('/api/v1/reports/export/payments');
        $res->assertOk();
        $this->assertStringContainsString('payment_date', $res->streamedContent());
    }

    public function test_export_paginates_through_large_dataset_and_does_not_mutate(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        Booking::factory()->count(12)->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value]);

        Sanctum::actingAs($admin);
        $res = $this->get('/api/v1/reports/export/bookings?per_page=5');

        $res->assertOk();
        $body = $res->streamedContent();
        $lines = array_filter(explode("\n", $body));
        $this->assertGreaterThan(7, count($lines), 'Chunk pagination harus memuat seluruh data (header + 12 baris).');

        // No mutation
        $this->assertDatabaseCount('bookings', 12);
        $this->assertEquals(BookingStatus::CONFIRMED->value, Booking::first()->status->value);
    }
}
