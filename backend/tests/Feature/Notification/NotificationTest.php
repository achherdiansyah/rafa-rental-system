<?php

namespace Tests\Feature\Notification;

use App\Actions\Booking\ApproveBookingAction;
use App\Actions\Payment\VerifyPaymentAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundSource;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Refund\RefundLifecycleService;
use App\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_service_creates_in_app_notification_with_related_entity(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::ISSUED->value,
            'grand_total' => 100000,
        ]);

        app(NotificationService::class)->send($user, 'INVOICE_ISSUED', $invoice, 'Invoice diterbitkan.');

        Sanctum::actingAs($user);
        $list = $this->getJson('/api/v1/notifications');
        $list->assertOk()->assertJsonPath('meta.total', 1);

        $row = $list->json('data.0');
        $this->assertEquals('INVOICE_ISSUED', $row['event']);
        $this->assertEquals(get_class($invoice), $row['entity_type']);
        $this->assertEquals($invoice->id, $row['entity_id']);
        $this->assertEquals('Invoice diterbitkan.', $row['message']);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()->assertJsonPath('data.count', 1);
    }

    public function test_unread_count_and_mark_as_read(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->send($user, 'A', null, 'satu');
        app(NotificationService::class)->sendToRole('USER', 'B', null, 'dua');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 2);

        $firstId = $this->getJson('/api/v1/notifications')->json('data.0.id');
        $this->postJson("/api/v1/notifications/{$firstId}/read")->assertOk();

        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 1);

        $this->postJson('/api/v1/notifications/read-all')->assertOk();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 0);
        $this->getJson('/api/v1/notifications?read=true')
            ->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_user_only_sees_and_reads_own_notifications(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        app(NotificationService::class)->send($alice, 'ALICE_EVENT', null, 'untuk alice');
        app(NotificationService::class)->send($bob, 'BOB_EVENT', null, 'untuk bob');

        Sanctum::actingAs($alice);
        $rows = $this->getJson('/api/v1/notifications')->json('data');
        $this->assertCount(1, $rows);
        $this->assertEquals('ALICE_EVENT', $rows[0]['event']);

        // Cannot read Bob's notification (scoped lookup -> 404)
        $bobId = $bob->notifications()->first()->id;
        $this->postJson("/api/v1/notifications/{$bobId}/read")->assertStatus(404);
        $this->assertNull($bob->notifications()->first()->read_at);
    }

    public function test_invoice_overdue_command_notifies_booking_owner(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::ISSUED->value,
            'due_at' => now()->subHour(),
            'grand_total' => 100000,
        ]);

        $this->artisan('invoices:expire')->assertSuccessful();

        $this->assertEquals(InvoiceStatus::OVERDUE->value, $invoice->fresh()->status->value);
        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertEquals('INVOICE_OVERDUE', $notification->data['event']);
    }

    public function test_booking_approval_and_payment_approval_notify_owner(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();

        // Booking approval notifies user
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'PENDING_APPROVAL']);
        app(ApproveBookingAction::class)->execute($admin, $booking);
        $this->assertEquals('BOOKING_APPROVED', $user->notifications()->latest()->first()->data['event']);

        // Payment approval notifies invoice booking owner
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

        $event = $user->notifications()->latest()->first()?->data['event'];
        $this->assertEquals('PAYMENT_APPROVED', $event);
    }

    public function test_refund_status_notifies_customer(): void
    {
        $user = User::factory()->create();
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
        $service->approve($owner, $refund, []);
        $progressed = $service->process($staff, $refund->fresh(), ['customer_bank_info' => 'BCA 123 a/n Customer']);
        $service->complete($staff, $progressed->fresh(), [], UploadedFile::fake()->image('resi.png', 600, 400));

        $events = $user->notifications()->get()->pluck('data.event')->all();
        $this->assertContains('REFUND_PROCESSING', $events);
        $this->assertContains('REFUND_COMPLETED', $events);
    }
}
