<?php

namespace Tests\Feature\Integration;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RentalStatus;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 13G: UAT & final regression. Simulates the primary customer journey
 * through the public APIs, then validates authorization, business rules and
 * the read-only reporting layer.
 */
class UatWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_full_customer_journey_with_roles_and_reports(): void
    {
        // ---------- 1) Register + login (auth flow) ----------
        $email = 'uat@example.com';
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dono UAT',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '081299887766',
        ]);
        $register->assertCreated();
        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);

        $login = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password123']);
        $login->assertOk();

        // KYC gate (Phase 6): submit booking requires a VERIFIED profile.
        CustomerProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['identity_type' => 'KTP', 'identity_number' => '3201234567890123', 'verification_status' => 'VERIFIED']
        );

        // ---------- 2) Recommendation / pricing available (equipment) ----------
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 150000.00,
            'mob_cost' => 400000.00,
            'demob_cost' => 250000.00,
            'effective_date' => now()->subMonth()->toDateString(),
        ]);

        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => 'AVAILABLE']);

        // ---------- 3) Project -> cart -> booking -------------
        Sanctum::actingAs($user);
        $projectId = $this->postJson('/api/v1/project-locations', [
            'project_name' => 'Tol UAT',
            'city' => 'Bekasi',
            'pic_name' => 'Dono UAT',
            'pic_phone' => '081299887766',
            'address' => 'Jl. Inspeksi Tol',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/cart/items', [
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'is_all_in' => true,
        ])->assertCreated();
        $this->putJson('/api/v1/cart/location', ['project_location_id' => $projectId])->assertOk();

        $bookingId = $this->postJson('/api/v1/bookings', [])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/bookings/{$bookingId}/submit")->assertOk();

        // ---------- 4) Admin approval + unit assignment + confirm ----------
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/bookings/{$bookingId}/approve")
            ->assertStatus(403); // authorization: user cannot approve

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/bookings/{$bookingId}/approve")->assertOk();
        $booking = Booking::find($bookingId);
        $this->assertEquals(BookingStatus::APPROVED->value, $booking->status->value);

        $detailId = $booking->details()->first()->id;
        $this->postJson("/api/v1/bookings/{$bookingId}/assign-units", [
            'assignments' => [['booking_detail_id' => $detailId, 'equipment_unit_id' => $unit->id]],
        ])->assertOk();
        $this->assertEquals('ASSIGNED', $unit->fresh()->status->value, 'Unit ditugaskan pada booking (reserved).');

        // UAT note (Phase 8): CONFIRMED tercapai via alur verifikasi pembayaran
        // (dikover test 10D); di sini state lanjut dipasang langsung.
        $booking->update(['status' => BookingStatus::CONFIRMED]);

        // ---------- 5) Dispatch -> arrival -> ongoing ----------
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $bookingId])->assertCreated()->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }
        $this->assertEquals('ONGOING', Rental::find($rentalId)->status->value);

        // ---------- 6) Timesheet + validation ----------
        $rd = Rental::find($rentalId)->details()->first();
        Sanctum::actingAs($admin);
        $tsId = $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 16,
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/timesheets/{$tsId}/submit")->assertOk();
        $this->postJson("/api/v1/timesheets/{$tsId}/approve")->assertOk();
        $this->assertEquals('APPROVED', Timesheet::find($tsId)->status->value);

        // ---------- 7) Return + inspection -> READY ----------
        foreach (['return', 'inspect'] as $t) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$t}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();
        $this->assertEquals(RentalStatus::COMPLETED->value, Rental::find($rentalId)->status->value);

        // ---------- 8) Invoice DAILY_WORK (8h x 150k) ----------
        $dailyInvoice = $this->postJson('/api/v1/invoices', [
            'booking_id' => $bookingId,
            'invoice_type' => 'DAILY_WORK',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$dailyInvoice}/issue")->assertOk();
        $this->assertEqualsWithDelta(1200000.0, (float) Invoice::find($dailyInvoice)->grand_total, 0.01);

        // ---------- 9) Payment: partial then full verification ----------
        Sanctum::actingAs($user);
        $bank = BankAccount::factory()->create();
        $payPartial = $this->postJson("/api/v1/invoices/{$dailyInvoice}/payments", [
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => $bank->id,
            'reference' => 'UAT-DP1',
            'proof' => UploadedFile::fake()->image('p1.png', 600, 300),
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/{$payPartial}/approve")->assertOk();
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID->value, Invoice::find($dailyInvoice)->fresh()->status->value);

        Sanctum::actingAs($user);
        $payFull = $this->postJson("/api/v1/invoices/{$dailyInvoice}/payments", [
            'amount' => 700000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => $bank->id,
            'reference' => 'UAT-DP2',
            'proof' => UploadedFile::fake()->image('p2.png', 600, 300),
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/{$payFull}/approve")->assertOk();
        $this->assertEquals(InvoiceStatus::PAID->value, Invoice::find($dailyInvoice)->fresh()->status->value);

        // ---------- 10) Overpayment (MOB/DEMOB) → refund settlement ----------
        $mobInvoice = $this->postJson('/api/v1/invoices', [
            'booking_id' => $bookingId,
            'invoice_type' => 'MOB_DEMOB',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/invoices/{$mobInvoice}/issue")->assertOk();
        // 400k + 250k = 650k; bayar 900k → overpay 250k
        Sanctum::actingAs($user);
        $overPayment = $this->postJson("/api/v1/invoices/{$mobInvoice}/payments", [
            'amount' => 900000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => $bank->id,
            'reference' => 'UAT-OVER',
            'proof' => UploadedFile::fake()->image('p3.png', 600, 300),
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/payments/{$overPayment}/approve")->assertOk();
        $this->assertEquals(InvoiceStatus::OVERPAID->value, Invoice::find($mobInvoice)->fresh()->status->value);

        $refund = Refund::where('invoice_id', $mobInvoice)->where('source', 'OVERPAYMENT')->first();
        $this->assertNotNull($refund);
        $this->assertEqualsWithDelta(250000.0, (float) $refund->amount, 0.01);

        // Admin cannot approve refund (owner-only)
        $this->postJson("/api/v1/refunds/{$refund->id}/approve", [])->assertStatus(403);
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/refunds/{$refund->id}/approve", ['approval_reason' => 'Overpayment sah.'])->assertOk();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/refunds/{$refund->id}/process", ['customer_bank_info' => 'BCA 001 a/n Dono UAT'])->assertOk();
        $this->postJson("/api/v1/refunds/{$refund->id}/complete", [
            'transfer_reference' => 'REF-UAT',
            'proof' => UploadedFile::fake()->image('resi.png', 600, 400),
        ])->assertOk();
        $this->assertEquals('COMPLETED', $refund->fresh()->status->value);

        // ---------- 11) Notification + outstanding ----------
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/notifications/unread-count')->assertOk();
        $count = $this->getJson('/api/v1/notifications/unread-count')->json('data.count');
        $this->assertGreaterThanOrEqual(2, $count, 'Harus ada notifikasi sepanjang alur.');
        $notif = $this->getJson('/api/v1/notifications')->assertOk();
        $events = collect($notif->json('data'))->pluck('event');
        $this->assertTrue($events->contains('INVOICE_ISSUED') || $events->contains('PAYMENT_APPROVED'));

        // ---------- 12) Dashboard / report / export (admin) ----------
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/reports/dashboard')->assertOk();
        $this->getJson('/api/v1/reports/operational/rentals')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/reports/financial/outstanding')->assertOk();
        $export = $this->get('/api/v1/reports/export/bookings?status=CONFIRMED');
        $export->assertOk();
        $this->assertStringContainsString('Tol UAT', $export->streamedContent());

        // ---------- 13) Error handling & business rules ----------
        // guest cannot access financial api
        $this->app->make(Factory::class)->forgetGuards();
        $this->getJson('/api/v1/invoices')->assertStatus(401);
        // duplicate timesheet rejected (business rule)
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/timesheets', [
            'rental_detail_id' => $rd->id,
            'report_date' => now()->toDateString(),
            'start_hm' => 8,
            'end_hm' => 16,
        ])->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }
}
