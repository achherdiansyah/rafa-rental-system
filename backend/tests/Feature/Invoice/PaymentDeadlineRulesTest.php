<?php

namespace Tests\Feature\Invoice;

use App\Actions\Booking\ApproveBookingAction;
use App\Actions\Booking\AssignBookingUnitsAction;
use App\Actions\Booking\ExpireBookingAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentDeadlineRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function setupApprovedBookingWithInvoice(): array
    {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 350000.00,
            'mob_cost' => 500000.00,
            'demob_cost' => 350000.00,
        ]);

        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::PENDING_APPROVAL,
            'total_amount' => 14850000.00,
        ]);

        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'is_all_in' => true,
            'rental_rate_snapshot' => 350000.00,
            'mob_cost_snapshot' => 500000.00,
            'demob_cost_snapshot' => 350000.00,
            'subtotal' => 14000000.00,
        ]);

        // Fix the time to a specific point to test exactly 24h
        Carbon::setTestNow(Carbon::create(2026, 10, 4, 14, 30, 0));

        app(ApproveBookingAction::class)->execute($admin, $booking);

        app(AssignBookingUnitsAction::class)->execute($admin, $booking, [
            ['booking_detail_id' => $detail->id, 'equipment_unit_id' => $unit->id]
        ]);

        $invoice = Invoice::where('booking_id', $booking->id)->firstOrFail();

        return [$owner, $admin, $booking, $invoice, $unit];
    }

    public function test_deadline_starts_from_issued_at_exactly_24_hours(): void
    {
        [, , $booking, $invoice] = $this->setupApprovedBookingWithInvoice();

        $issuedAt = $invoice->issued_at;
        $dueAt = $invoice->due_at;

        // 1. Deadline from issued_at
        $this->assertNotNull($issuedAt);
        $this->assertEquals('2026-10-04 14:30:00', $issuedAt->format('Y-m-d H:i:s'));

        // 2. Exactly 24 hours
        $this->assertNotNull($dueAt);
        $this->assertEquals('2026-10-05 14:30:00', $dueAt->format('Y-m-d H:i:s'));
        $this->assertEquals(24, $issuedAt->diffInHours($dueAt));
        $this->assertEquals($booking->payment_deadline_at->timestamp, $dueAt->timestamp);

        Carbon::setTestNow();
    }

    public function test_payment_rejected_does_not_reset_deadline(): void
    {
        [$owner, $admin, $booking, $invoice] = $this->setupApprovedBookingWithInvoice();
        
        $originalDueAt = $invoice->due_at;

        // Advance time 5 hours later
        Carbon::setTestNow($originalDueAt->copy()->subHours(19));

        Sanctum::actingAs($owner);
        $res = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 850000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'reference' => 'TRF-1',
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ]);
        $paymentId = $res->json('data.id');

        // Admin rejects
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/{$paymentId}/reject", [
            'reason' => 'Bukti buram'
        ])->assertOk();

        $invoice->refresh();
        $this->assertEquals($originalDueAt->timestamp, $invoice->due_at->timestamp, 'Deadline tidak boleh kereset setelah reject payment');

        Carbon::setTestNow();
    }

    public function test_expired_deadline_expires_booking_and_releases_unit(): void
    {
        [, , $booking, $invoice, $unit] = $this->setupApprovedBookingWithInvoice();

        // Advance time 25 hours later (deadline crossed)
        Carbon::setTestNow($invoice->due_at->copy()->addHour());

        app(ExpireBookingAction::class)->execute($booking);

        $booking->refresh();
        $unit->refresh();

        $this->assertEquals(BookingStatus::EXPIRED->value, $booking->status->value);
        $this->assertEquals(EquipmentStatus::AVAILABLE->value, $unit->status->value);
        
        // Ensure no current assignments left
        $currentCount = BookingUnitAssignment::where('booking_detail_id', $booking->details->first()->id)
            ->where('is_current', true)
            ->count();
        $this->assertEquals(0, $currentCount);

        Carbon::setTestNow();
    }

    public function test_admin_manual_extension_updates_deadline(): void
    {
        [, $admin, $booking, $invoice] = $this->setupApprovedBookingWithInvoice();
        
        $originalDueAt = $invoice->due_at;

        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/v1/invoices/{$invoice->id}/extend-deadline", [
            'hours' => 12
        ]);
        $res->assertOk();

        $invoice->refresh();
        $booking->refresh();

        $this->assertEquals(
            $originalDueAt->copy()->addHours(12)->timestamp,
            $invoice->due_at->timestamp
        );
        $this->assertEquals(
            $originalDueAt->copy()->addHours(12)->timestamp,
            $booking->payment_deadline_at->timestamp
        );

        Carbon::setTestNow();
    }
}