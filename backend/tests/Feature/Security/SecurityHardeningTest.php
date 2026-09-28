<?php

namespace Tests\Feature\Security;

use App\Enums\BookingStatus;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_tokens_expire_by_default(): void
    {
        $this->assertEquals(10080, (int) config('sanctum.expiration'));
        $this->assertEquals(config('sanctum.expiration'), (int) env('SANCTUM_EXPIRATION', 10080));
    }

    public function test_auth_login_is_rate_limited(): void
    {
        // 5 login attempts per minute (throttle:auth, per IP); isolate with a
        // dedicated REMOTE_ADDR so other suites' login tests don't consume it.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);

        for ($i = 0; $i < 5; $i++) {
            $res = $this->postJson('/api/v1/auth/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
            $this->assertContains($res->getStatusCode(), [401, 422], 'Kredensial salah harus ditolak (401/422).');
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_payment_upload_is_rate_limited(): void
    {
        $owner = User::factory()->create(['role' => 'USER']);
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create(['user_id' => $owner->id, 'status' => BookingStatus::CONFIRMED->value]);
        $invoice = Invoice::factory()->create(['booking_id' => $booking->id, 'status' => 'ISSUED', 'grand_total' => 1000000]);
        $bank = BankAccount::factory()->create();

        // file-upload limiter: 10 uploads per minute
        for ($i = 0; $i < 10; $i++) {
            Sanctum::actingAs($owner);
            $res = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
                'amount' => 10000,
                'payment_date' => now()->toDateString(),
                'bank_account_id' => $bank->id,
                'reference' => "TRF-RL-{$i}",
                'proof' => UploadedFile::fake()->image("b{$i}.png", 400, 200),
            ]);
            if ($i < 10) {
                $res->assertCreated();
            }
        }

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 10000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => $bank->id,
            'reference' => 'TRF-RL-OVER',
            'proof' => UploadedFile::fake()->image('over.png', 400, 200),
        ])->assertStatus(429);
    }

    public function test_production_error_response_hides_stack_trace(): void
    {
        config(['app.debug' => false]);

        Route::get('probe-hidden-error', function () {
            throw new \RuntimeException('SECRET_INTERNAL_TRACE');
        });

        $res = $this->getJson('/probe-hidden-error');

        $res->assertStatus(500);
        $body = $res->getContent();
        $this->assertStringNotContainsString('SECRET_INTERNAL_TRACE', $body);
        $this->assertStringNotContainsString('stacktrace', strtolower($body));
        $this->assertStringContainsString('internal server error', strtolower($body));
    }

    public function test_mass_assignment_cannot_escalate_role(): void
    {
        $user = User::factory()->create(['role' => 'USER']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'name' => 'Hijacker',
            'role' => 'OWNER',
            'phone_number' => $user->phone_number,
        ])->assertOk();

        $this->assertEquals('USER', $user->fresh()->role->value, 'Role tidak boleh berubah via mass assignment.');
    }

    public function test_private_proof_is_not_publicly_exposed(): void
    {
        // Public attachment URL stays null; private disk path is not under public storage.
        $invoice = Invoice::factory()->create(['status' => 'ISSUED', 'grand_total' => 100000]);
        $owner = User::factory()->create();
        $invoice->update(['booking_id' => Booking::factory()->create(['user_id' => $owner->id, 'status' => 'CONFIRMED'])->id]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'bank_account_id' => BankAccount::factory()->create()->id,
            'reference' => 'TRF-PRIV',
            'proof' => UploadedFile::fake()->image('p.png', 400, 200),
        ])->assertCreated()->assertJsonPath('data.proof.url', null);
    }
}
