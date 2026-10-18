<?php

namespace Tests\Feature\Finance;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\RentalStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\RentalPaymentHold;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentHoldTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{rental: Rental, detail: RentalDetail, unit: EquipmentUnit, admin: User}
     */
    private function setupRental(): array
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['role' => 'USER']);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ON_SITE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'project_location_id' => ProjectLocation::factory()->create(['user_id' => $customer->id])->id,
            'status' => BookingStatus::ONGOING->value,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
        ]);
        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $rental = Rental::factory()->create([
            'booking_id' => $booking->id,
            'status' => RentalStatus::ONGOING->value,
        ]);
        $rentalDetail = RentalDetail::factory()->create([
            'rental_id' => $rental->id,
            'assignment_id' => $assignment->id,
            'status' => RentalStatus::ONGOING->value,
        ]);

        return compact('rental', 'unit', 'admin') + ['detail' => $rentalDetail];
    }

    private int $invoiceSeq = 0;

    private function dailyInvoice(Rental $rental, RentalDetail $detail, string $number, ?string $issuedAt = null, float $paid = 0): Invoice
    {
        $timesheet = Timesheet::factory()->create([
            'rental_detail_id' => $detail->id,
            'report_date' => now()->subDays($this->invoiceSeq++)->toDateString(),
        ]);

        return Invoice::create([
            'invoice_number' => $number,
            'booking_id' => $rental->booking_id,
            'timesheet_id' => $timesheet->id,
            'invoice_type' => InvoiceType::DAILY_WORK,
            'status' => $paid > 0 ? InvoiceStatus::PARTIALLY_PAID : InvoiceStatus::ISSUED,
            'issued_at' => $issuedAt ?? now()->toDateTimeString(),
            'due_at' => now()->addHours(24),
            'subtotal' => 1000000,
            'tax_total' => 0,
            'grand_total' => 1000000,
            'paid_amount' => $paid,
            'overpayment_amount' => 0,
        ]);
    }

    public function test_three_unpaid_daily_invoices_start_hold_without_changing_rental_or_unit(): void
    {
        ['rental' => $rental, 'detail' => $detail, 'unit' => $unit, 'admin' => $admin] = $this->setupRental();

        foreach (['INV/H1', 'INV/H2', 'INV/H3'] as $i => $number) {
            $this->dailyInvoice($rental, $detail, $number, now()->subHours($i)->toDateTimeString());
        }

        $this->artisan('rentals:evaluate-payment-holds')->assertSuccessful();

        $hold = RentalPaymentHold::where('rental_id', $rental->id)->where('status', 'ACTIVE')->firstOrFail();
        $this->assertSame(RentalPaymentHold::REASON_INVOICE_COUNT, $hold->reason_code);
        $this->assertEquals(RentalStatus::ONGOING, $rental->fresh()->status);
        $this->assertEquals(EquipmentStatus::ON_SITE, $unit->fresh()->status);

        $this->artisan('rentals:evaluate-payment-holds')->assertSuccessful();
        $this->assertSame(1, RentalPaymentHold::where('rental_id', $rental->id)->count());

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/rentals/{$rental->id}/payment-hold/resume", ['reason' => 'Coba resume dulu.'])
            ->assertStatus(409);
    }

    public function test_oldest_unpaid_invoice_over_72_hours_starts_hold(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->setupRental();

        $this->dailyInvoice($rental, $detail, 'INV/OLD1', now()->subHours(73)->toDateTimeString());
        $this->dailyInvoice($rental, $detail, 'INV/OLD2', now()->subHours(2)->toDateTimeString());

        $this->artisan('rentals:evaluate-payment-holds')->assertSuccessful();

        $hold = RentalPaymentHold::where('rental_id', $rental->id)->where('status', 'ACTIVE')->firstOrFail();
        $this->assertSame(RentalPaymentHold::REASON_INVOICE_AGE, $hold->reason_code);
    }

    public function test_no_hold_below_threshold_and_resume_after_invoices_paid(): void
    {
        ['rental' => $rental, 'detail' => $detail, 'admin' => $admin] = $this->setupRental();

        $invoices = [
            $this->dailyInvoice($rental, $detail, 'INV/N1'),
            $this->dailyInvoice($rental, $detail, 'INV/N2'),
        ];

        $this->artisan('rentals:evaluate-payment-holds')->assertSuccessful();
        $this->assertSame(0, RentalPaymentHold::where('rental_id', $rental->id)->count());

        $invoices[] = $this->dailyInvoice($rental, $detail, 'INV/N3');
        $this->artisan('rentals:evaluate-payment-holds')->assertSuccessful();
        $this->assertSame(1, RentalPaymentHold::where('rental_id', $rental->id)->where('status', 'ACTIVE')->count());

        foreach ($invoices as $invoice) {
            $invoice->update(['paid_amount' => $invoice->grand_total, 'status' => InvoiceStatus::PAID]);
        }

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/rentals/{$rental->id}/payment-hold/resume", [
            'reason' => 'Seluruh invoice telah lunas dan diverifikasi.',
        ])->assertOk();

        $this->assertSame(0, RentalPaymentHold::where('rental_id', $rental->id)->where('status', 'ACTIVE')->count());
        $this->assertEquals(RentalStatus::ONGOING, $rental->fresh()->status);
    }

    public function test_partial_payment_still_counts_as_outstanding(): void
    {
        ['rental' => $rental, 'detail' => $detail] = $this->setupRental();

        $this->dailyInvoice($rental, $detail, 'INV/P1', null, 100000);
        $this->dailyInvoice($rental, $detail, 'INV/P2', null, 200000);
        $this->dailyInvoice($rental, $detail, 'INV/P3', null, 300000);

        $this->artisan('rentals:evaluate-payment-holds')->assertSuccessful();

        $this->assertSame(1, RentalPaymentHold::where('rental_id', $rental->id)->where('status', 'ACTIVE')->count());
    }
}
