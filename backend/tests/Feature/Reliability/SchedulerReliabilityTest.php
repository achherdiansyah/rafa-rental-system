<?php

namespace Tests\Feature\Reliability;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class SchedulerReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_commands_are_registered_for_shared_hosting(): void
    {
        $commands = collect(Schedule::events())
            ->map(fn ($event) => $event->command)
            ->filter()
            ->values()
            ->all();

        foreach (['bookings:expire', 'invoices:expire', 'outstanding:remind', 'db:backup'] as $signature) {
            $this->assertTrue(
                in_array("'{$signature}'", $commands, true) || $this->containsSignature($commands, $signature),
                "Scheduler harus mendaftarkan {$signature}."
            );
        }
    }

    private function containsSignature(array $commands, string $signature): bool
    {
        foreach ($commands as $command) {
            if (is_string($command) && str_contains($command, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function test_booking_and_invoice_expiry_are_idempotent(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'status' => BookingStatus::APPROVED->value,
            'payment_deadline_at' => now()->subMinutes(5),
        ]);
        $invoice = Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::ISSUED->value,
            'due_at' => now()->subMinutes(5),
            'grand_total' => 100000,
        ]);

        // First pass expires both
        $this->artisan('bookings:expire')->assertSuccessful();
        $this->artisan('invoices:expire')->assertSuccessful();
        $this->assertEquals(BookingStatus::EXPIRED->value, $booking->fresh()->status->value);
        $this->assertEquals(InvoiceStatus::OVERDUE->value, $invoice->fresh()->status->value);

        // Idempotent: second pass changes nothing / does not duplicate processing
        $this->artisan('bookings:expire')->assertSuccessful();
        $this->artisan('invoices:expire')->assertSuccessful();
        $this->assertEquals(BookingStatus::EXPIRED->value, $booking->fresh()->status->value);
        $this->assertEquals(InvoiceStatus::OVERDUE->value, $invoice->fresh()->status->value);
    }

    public function test_outstanding_reminder_same_day_guard_prevents_duplicates(): void
    {
        $user = User::factory()->create(['phone_number' => '081234567890']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'CONFIRMED']);
        Invoice::factory()->create([
            'booking_id' => $booking->id,
            'status' => InvoiceStatus::OVERDUE->value,
            'due_at' => now()->subDay(),
            'grand_total' => 500000,
        ]);

        $this->artisan('outstanding:remind')->assertSuccessful();
        $this->artisan('outstanding:remind')->assertSuccessful();

        $deliveries = NotificationDelivery::where('event', 'OUTSTANDING_REMINDER')
            ->where('recipient_id', $user->id)
            ->count();
        $this->assertEquals(1, $deliveries, 'Reminder tidak boleh duplikat di hari yang sama.');
    }

    public function test_whatsapp_delivery_job_is_fail_fast_no_retry_duplication(): void
    {
        $job = new SendWhatsAppNotification(
            new WhatsAppMessage('081234567890', 'pesan', 'PAYMENT_APPROVED'),
            1,
            'fake'
        );

        $this->assertEquals(1, $job->tries, 'Job WA harus fail-fast agar tidak menduplikasi delivery.');
    }
}
