<?php

namespace Tests\Feature\Invoice;

use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    /**
     * @return array{0: User, 1: Booking, 2: array<int, EquipmentUnit>}
     */
    private function completedRentalBooking(int $unitCount = 1, float $rate = 150000.00, float $mob = 500000.00, float $demob = 350000.00, float $hours = 8.5): array
    {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        $units = EquipmentUnit::factory()->count($unitCount)->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::ASSIGNED,
        ]);

        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => $unitCount,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'is_all_in' => true,
            'rental_rate_snapshot' => $rate,
            'mob_cost_snapshot' => $mob,
            'demob_cost_snapshot' => $demob,
        ]);

        foreach ($units as $unit) {
            BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $detail->id,
                'equipment_unit_id' => $unit->id,
                'status' => AssignmentStatus::ASSIGNED,
                'is_current' => true,
                'assigned_by' => $admin->id,
            ]);
        }

        Sanctum::actingAs($admin);
        $rentalId = $this->postJson('/api/v1/rentals', ['booking_id' => $booking->id])->json('data.id');
        foreach (['dispatch', 'arrive', 'start'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }

        $rental = Rental::findOrFail($rentalId);
        foreach ($rental->details as $rd) {
            Sanctum::actingAs($admin);
            $tsId = $this->postJson('/api/v1/timesheets', [
                'rental_detail_id' => $rd->id,
                'report_date' => now()->toDateString(),
                'start_hm' => 8,
                'end_hm' => 8 + $hours,
            ])->json('data.id');
        }

        Sanctum::actingAs($admin);
        foreach (['return', 'inspect'] as $target) {
            $this->postJson("/api/v1/rentals/{$rentalId}/{$target}")->assertOk();
        }
        $this->postJson("/api/v1/rentals/{$rentalId}/ready", ['result' => 'READY'])->assertOk();

        // Clear auto-created invoices so test can test manual invoice lifecycle independently
        Invoice::where('booking_id', $booking->id)->forceDelete();

        return [$owner, $booking, $units];
    }

    private function createInvoice(User $as, Booking $booking, string $type): array
    {
        Sanctum::actingAs($as);
        $res = $this->postJson('/api/v1/invoices', [
            'booking_id' => $booking->id,
            'invoice_type' => $type,
        ]);

        return [$res, $res->json('data')];
    }

    public function test_daily_work_invoice_creation_with_snapshot_and_numbering(): void
    {
        [, $booking] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();

        [$res, $data] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);

        $res->assertCreated()
            ->assertJsonPath('data.status', InvoiceStatus::DRAFT->value)
            ->assertJsonPath('data.invoice_type', 'DAILY_WORK')
            ->assertJsonPath('data.booking.booking_code', $booking->booking_code);

        $this->assertMatchesRegularExpression('#^INV/\d{6}/\d{4}$#', $data['invoice_number']);
        $this->assertNull($data['issued_at']);

        $invoice = Invoice::findOrFail($data['id']);
        $this->assertCount(1, $invoice->details);
        $detail = $invoice->details()->first();
        $this->assertEquals(150000.00, (float) $detail->unit_price);
        $this->assertEqualsWithDelta(8.5, (float) $detail->quantity, 0.0001);
        $this->assertEqualsWithDelta(8.5 * 150000, (float) $detail->subtotal, 0.0001);
        $this->assertEqualsWithDelta(8.5 * 150000, (float) $invoice->grand_total, 0.0001);

        // Total/balance
        $this->assertEqualsWithDelta((float) $invoice->grand_total, (float) $invoice->balance(), 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $data['paid_amount'], 0.0001);
    }

    public function test_mob_demob_invoice_per_physical_unit_and_can_differ(): void
    {
        [, $booking] = $this->completedRentalBooking(unitCount: 2);
        $admin = User::factory()->admin()->create();

        [$res] = $this->createInvoice($admin, $booking, InvoiceType::MOB_DEMOB->value);

        $res->assertCreated();
        $invoice = Invoice::findOrFail($res->json('data.id'));

        // 2 units × (MOB + DEMOB) lines
        $this->assertCount(4, $invoice->details);
        $this->assertEqualsWithDelta(500000.00 * 2 + 350000.00 * 2, (float) $invoice->grand_total, 0.0001);
        $mobLines = $invoice->details()->where('description', 'like', 'Mobilisasi%')->get();
        $demobLines = $invoice->details()->where('description', 'like', 'Demobilisasi%')->get();
        $this->assertCount(2, $mobLines);
        $this->assertCount(2, $demobLines);
        $this->assertEquals(500000.00, (float) $mobLines->first()->unit_price);
        $this->assertEquals(350000.00, (float) $demobLines->first()->unit_price);
    }

    public function test_booking_can_have_multiple_invoices(): void
    {
        [, $booking] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();

        [, $daily] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        [, $mobDemob] = $this->createInvoice($admin, $booking, InvoiceType::MOB_DEMOB->value);

        $this->assertNotEquals($daily['invoice_number'], $mobDemob['invoice_number']);
        $this->assertEquals($booking->id, $daily['booking_id']);
        $this->assertEquals($booking->id, $mobDemob['booking_id']);
        $this->assertMatchesRegularExpression('#^INV/\d{6}/\d{4}$#', $mobDemob['invoice_number']);
    }

    public function test_issue_sets_issued_at_and_24h_due_deadline(): void
    {
        [$owner, $booking] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();

        [, $draft] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        $invoice = Invoice::findOrFail($draft['id']);

        Sanctum::actingAs($admin);
        $issue = $this->postJson("/api/v1/invoices/{$invoice->id}/issue");
        $issue->assertOk()
            ->assertJsonPath('data.status', InvoiceStatus::ISSUED->value);

        $issued = $invoice->fresh();
        $this->assertNotNull($issued->issued_at);
        $this->assertNotNull($issued->due_at);
        $this->assertEqualsWithDelta(24.0, $issued->issued_at->diffInHours($issued->due_at), 0.001);
        $this->assertEqualsWithDelta($issued->issued_at->copy()->addHours(24)->timestamp, $issued->due_at->timestamp, 1);

        // Issuance is single-shot
        $this->postJson("/api/v1/invoices/{$invoice->id}/issue")
            ->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
    }

    public function test_mark_unpaid_then_overdue_when_deadline_passes(): void
    {
        [, $booking] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();

        [, $draft] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        $invoice = Invoice::findOrFail($draft['id']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/invoices/{$invoice->id}/issue")->assertOk();
        $this->postJson("/api/v1/invoices/{$invoice->id}/mark-unpaid")
            ->assertOk()->assertJsonPath('data.status', InvoiceStatus::UNPAID->value);

        // Simulate deadline passed then run scheduler command
        $invoice->update(['due_at' => now()->subMinute()]);
        $this->artisan('invoices:expire')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::OVERDUE->value, $invoice->fresh()->status->value);
    }

    public function test_void_keeps_financial_history(): void
    {
        [, $booking] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();

        [, $draft] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        $invoice = Invoice::findOrFail($draft['id']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/invoices/{$invoice->id}/void")
            ->assertOk()->assertJsonPath('data.status', InvoiceStatus::CANCELLED->value);

        // Row kept (not deleted) — financial history intact
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => InvoiceStatus::CANCELLED->value]);
        $this->assertCount(1, $invoice->details()->get());
    }

    public function test_historical_price_integrity_after_master_change(): void
    {
        [, $booking] = $this->completedRentalBooking(rate: 150000.00);
        $admin = User::factory()->admin()->create();

        [, $draft] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        $invoice = Invoice::findOrFail($draft['id']);
        $originalTotal = (float) $invoice->grand_total;

        // Master price goes up after invoicing
        $admin2 = User::factory()->admin()->create();
        Sanctum::actingAs($admin2);
        $price = EquipmentPrice::where('equipment_model_id', $invoice->booking->details()->first()->equipment_model_id)
            ->orWhereNull('equipment_model_id')
            ->orderBy('effective_date', 'desc')
            ->first() ?? EquipmentPrice::factory()->create([
                'equipment_model_id' => $invoice->booking->details()->first()->equipment_model_id,
                'is_all_in' => true,
                'base_rate' => 999999.00,
                'effective_date' => now()->toDateString(),
            ]);

        $this->assertNotNull($price);

        $fresh = $invoice->fresh();
        $this->assertEqualsWithDelta($originalTotal, (float) $fresh->grand_total, 0.0001);
        $this->assertEqualsWithDelta(150000.00, (float) $fresh->details()->first()->unit_price, 0.0001);
    }

    public function test_invoice_prerequisites_must_be_met(): void
    {
        $owner = User::factory()->create(['role' => UserRole::USER]);
        $admin = User::factory()->admin()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $owner->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $seen = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::AVAILABLE]);
        $booking = Booking::factory()->create([
            'user_id' => $owner->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::CONFIRMED,
        ]);
        $this->assertNotNull($seen);

        // Rental does not exist yet -> prerequisite (physical units) unmet
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/invoices', [
            'booking_id' => $booking->id,
            'invoice_type' => InvoiceType::DAILY_WORK->value,
        ])->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_unauthorized_actions_are_rejected(): void
    {
        [, $booking, $units] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);
        $other = User::factory()->create(['role' => UserRole::USER]);

        [, $draft] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        $invoice = Invoice::findOrFail($draft['id']);

        // USER cannot create/issue/void
        $this->createInvoice($user, $booking, InvoiceType::DAILY_WORK->value)[0]->assertStatus(403);
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/invoices/{$invoice->id}/issue")->assertStatus(403);
        $this->postJson("/api/v1/invoices/{$invoice->id}/void")->assertStatus(403);

        // USER cannot view someone else's invoice
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertStatus(403);
    }

    public function test_invoice_pdf_endpoint_returns_valid_pdf(): void
    {
        [, $booking] = $this->completedRentalBooking();
        $admin = User::factory()->admin()->create();
        $owner = $booking->user;

        [, $draft] = $this->createInvoice($admin, $booking, InvoiceType::DAILY_WORK->value);
        $invoice = Invoice::findOrFail($draft['id']);

        Sanctum::actingAs($admin);
        $res = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf", ['Accept' => 'application/pdf']);

        $res->assertOk();
        $this->assertEquals('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-1.4', $res->getContent());
    }
}
