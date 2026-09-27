<?php

namespace Tests\Feature\Notification;

use App\Actions\Payment\VerifyPaymentAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundLifecycleService;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FakeWhatsAppGateway implements WhatsAppGateway
{
    public bool $sentResult = true;

    public ?string $errorResult = null;

    public function name(): string
    {
        return 'fake-wa';
    }

    public function send(WhatsAppMessage $message): array
    {
        return ['sent' => $this->sentResult, 'error' => $this->errorResult];
    }
}

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPhone(): User
    {
        return User::factory()->create(['phone_number' => '081234567890']);
    }

    public function test_unconfigured_provider_logs_skipped_without_fake_success(): void
    {
        $user = $this->userWithPhone();

        $result = app(WhatsAppNotifier::class)->notify($user, 'PAYMENT_APPROVED', 'pesan uji');
        $result->refresh();

        $this->assertEquals('SKIPPED', $result->status);
        $this->assertStringContainsString('belum dikonfigurasi', (string) $result->error);
        $this->assertNull($result->sent_at);

        // System keeps working: next event records too, no exception.
        app(WhatsAppNotifier::class)->notify($user, 'INVOICE_ISSUED', 'lagi');
        $this->assertCount(2, NotificationDelivery::all());
    }

    public function test_missing_phone_is_skipped(): void
    {
        $user = User::factory()->create(['phone_number' => null]);

        $result = app(WhatsAppNotifier::class)->notify($user, 'REFUND_COMPLETED', 'refund selesai');

        $this->assertEquals('SKIPPED', $result->status);
        $this->assertStringContainsString('Nomor WhatsApp', (string) $result->error);
    }

    public function test_sent_and_failed_are_persisted_with_provider(): void
    {
        $fake = new FakeWhatsAppGateway;
        $fake->sentResult = true;
        $this->app->instance(WhatsAppGateway::class, $fake);

        $user = $this->userWithPhone();
        $sent = app(WhatsAppNotifier::class)->notify($user, 'PAYMENT_APPROVED', 'ok tersetujui');
        $this->assertEquals('SENT', $sent->status);
        $this->assertEquals('fake-wa', $sent->provider);
        $this->assertNotNull($sent->sent_at);
        $this->assertNull($sent->error);

        // Failure path
        $fake->sentResult = false;
        $fake->errorResult = 'API timeout';
        $failed = app(WhatsAppNotifier::class)->notify($user, 'PAYMENT_REJECTED', 'coba lagi');
        $this->assertEquals('FAILED', $failed->status);
        $this->assertEquals('API timeout', $failed->error);
        $this->assertNull($failed->sent_at);
    }

    public function test_payment_approval_wires_whatsapp_event(): void
    {
        $user = $this->userWithPhone();
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::UNPAID->value,
            'grand_total' => 800000,
        ]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => 800000,
            'status' => PaymentStatus::SUBMITTED,
        ]);

        Sanctum::actingAs($admin);
        app(VerifyPaymentAction::class)->approve($admin, $payment);

        $delivery = NotificationDelivery::where('event', 'PAYMENT_APPROVED')->where('channel', 'whatsapp')->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('SKIPPED', $delivery->status, 'Provider belum tersedia ⇒ SKIPPED (bukan SENT palsu).');
        $this->assertEquals($user->id, $delivery->recipient_id);
    }

    public function test_refund_completed_wires_whatsapp_event(): void
    {
        $user = $this->userWithPhone();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::OVERPAID->value,
            'overpayment_amount' => 200000,
            'paid_amount' => 800000,
            'grand_total' => 800000,
        ]);
        $refund = Refund::factory()->create([
            'invoice_id' => $invoice->id,
            'source' => RefundSource::OVERPAYMENT,
            'status' => RefundStatus::PENDING,
            'amount' => 200000,
        ]);
        $owner = User::factory()->owner()->create();
        $staff = User::factory()->admin()->create();

        $service = app(RefundLifecycleService::class);
        $refund = $service->approve($owner, $refund, []);
        $refund = $service->process($staff, $refund->fresh(), ['customer_bank_info' => 'BCA 123 a/n Customer']);
        $service->complete($staff, $refund->fresh(), [], UploadedFile::fake()->image('resi.png', 600, 400));

        $delivery = NotificationDelivery::where('event', 'REFUND_COMPLETED')->where('channel', 'whatsapp')->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('SKIPPED', $delivery->status);
        $this->assertEquals($user->id, $delivery->recipient_id);
    }

    public function test_outstanding_reminder_command_records_delivery_for_debtor(): void
    {
        $user = $this->userWithPhone();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::OVERDUE->value,
            'due_at' => now()->subDay(),
            'grand_total' => 500000,
        ]);

        $this->artisan('outstanding:remind')->assertSuccessful();

        $delivery = NotificationDelivery::where('event', 'OUTSTANDING_REMINDER')->where('channel', 'whatsapp')->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('SKIPPED', $delivery->status);
        $this->assertEquals($user->id, $delivery->recipient_id);
    }
}
