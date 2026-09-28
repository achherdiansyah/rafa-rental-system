<?php

namespace Tests\Feature\Storage;

use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RentalDetail;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Refund\RefundLifecycleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileStorageIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function issuedInvoice(): array
    {
        $owner = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id, 'status' => 'CONFIRMED']);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'ISSUED',
            'grand_total' => 500000,
        ]);

        return [$owner, $invoice];
    }

    public function test_payment_proof_private_upload_and_authorized_download(): void
    {
        [$owner, $invoice] = $this->issuedInvoice();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($owner);
        $res = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'reference' => 'TRF-SEC',
            'proof' => UploadedFile::fake()->image('bukti.png', 600, 300),
        ]);
        $res->assertCreated();
        $this->assertNull($res->json('data.proof.url'), 'Private proof tidak tersedia di public URL.');

        $payment = Payment::find($res->json('data.id'));
        $proof = $payment->proof();
        $this->assertNotNull($proof);
        Storage::disk('local')->assertExists($proof->file_path);
        $this->assertStringNotContainsString('public/', $proof->file_path);

        // Owner & admin download; stranger cannot
        $dlOwner = $this->getJson("/api/v1/payments/{$payment->id}/proof");
        $dlOwner->assertOk();
        $this->assertStringContainsString('image/png', $dlOwner->headers->get('content-type'));

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/payments/{$payment->id}/proof")->assertStatus(403);
    }

    public function test_refund_proof_private_and_authorized_download(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->admin()->create();
        $ownerOwner = User::factory()->owner()->create();
        $stranger = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'booking_id' => Booking::factory()->create(['user_id' => $owner->id, 'status' => 'CONFIRMED'])->id,
            'status' => 'OVERPAID',
            'paid_amount' => 800000,
            'overpayment_amount' => 200000,
            'grand_total' => 800000,
        ]);
        $refund = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'status' => RefundStatus::PENDING,
            'amount' => 200000,
        ]);

        $service = app(RefundLifecycleService::class);
        $refund = $service->approve($ownerOwner, $refund, []);
        $refund = $service->process($staff, $refund->fresh(), ['customer_bank_info' => 'BCA 123']);
        $service->complete($staff, $refund->fresh(), [], UploadedFile::fake()->image('resi.png', 600, 400));

        $proof = $refund->fresh()->attachments()->where('document_type', 'REFUND_PROOF')->first();
        $this->assertNotNull($proof);
        Storage::disk('local')->assertExists($proof->file_path);

        Sanctum::actingAs($owner);
        $res = $this->getJson("/api/v1/refunds/{$refund->id}/proof");
        $res->assertOk();
        $this->assertStringContainsString('image/png', $res->headers->get('content-type'));

        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/refunds/{$refund->id}/proof")->assertStatus(403);

        // Invalid file rejected at completion
        $refund2 = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'status' => RefundStatus::APPROVED,
            'amount' => 0,
        ]);
        $this->expectException(InvalidStateTransitionException::class);
        $service->complete($staff, $refund2, [], UploadedFile::fake()->create('evil.php', 100, 'application/x-php'));
    }

    public function test_orhpan_audit_reports_missing_and_orphans_without_deleting(): void
    {
        Storage::disk('local')->put('payments/orphan.png', 'x');
        Attachment::create([
            'attachable_type' => Payment::class,
            'attachable_id' => 1,
            'document_type' => 'PAYMENT_PROOF',
            'file_path' => 'payments/missing-bukti.png',
            'file_name' => 'missing.png',
            'mime_type' => 'image/png',
            'file_size' => 1,
            'uploaded_by' => User::factory()->create()->id,
        ]);
        // Ensure the orphan file is older than 1 day
        touch(Storage::disk('local')->path('payments/orphan.png'), now()->subDays(3)->getTimestamp());

        $this->artisan('storage:audit-orphans')
            ->expectsOutputToContain('MISSING')
            ->expectsOutputToContain('ORPHAN')
            ->assertSuccessful();

        $this->assertTrue(Storage::disk('local')->exists('payments/orphan.png'), 'Audit tidak boleh menghapus file.');
        $this->assertDatabaseHas('attachments', ['file_path' => 'payments/missing-bukti.png']);
    }

    public function test_unique_and_fk_constraints_protect_integrity(): void
    {
        $owner = User::factory()->create();
        $contract = RentalDetail::factory()->create();
        Timesheet::factory()->create(['rental_detail_id' => $contract->id, 'report_date' => '2026-09-10']);
        $unique = new Timesheet;
        $unique->forceFill(['rental_detail_id' => $contract->id, 'report_date' => '2026-09-10', 'start_hm' => 8, 'end_hm' => 9, 'status' => 'DRAFT']);

        $this->expectException(QueryException::class);
        $unique->save();
    }

    public function test_financial_rows_not_hard_deletable_via_fk_restrict(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'ISSUED', 'grand_total' => 100000]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'status' => 'SUBMITTED']);
        Refund::factory()->create(['invoice_id' => $invoice->id, 'status' => RefundStatus::PENDING]);

        // Soft delete keeps history; forced delete is blocked by FK RESTRICT.
        $invoice->delete();
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('refunds', 1);

        $this->expectException(QueryException::class);
        $invoice->forceDelete(); // payments/refunds FK RESTRICT blocks removal
    }
}
