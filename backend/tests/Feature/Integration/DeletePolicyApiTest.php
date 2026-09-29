<?php

namespace Tests\Feature\Integration;

use App\Enums\UserRole;
use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pre-Phase 14 E2E: hard-delete policy for financial/history entities.
 *
 * Invoices, payments, refunds, rentals, timesheets and bookings carry
 * immutable financial/historical meaning and have NO delete routes by design.
 * Every delete attempt must be rejected (404 route-missing), never a hard
 * delete. Accounts that are still referenced by transactions follow the safe
 * same policy (409 + history intact).
 */
class DeletePolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_and_history_entities_have_no_delete_route(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $responses = [
            '/api/v1/bookings/1',
            '/api/v1/rentals/1',
            '/api/v1/timesheets/1',
            '/api/v1/invoices/1',
            '/api/v1/payments/1',
            '/api/v1/refunds/1',
        ];

        foreach ($responses as $url) {
            // No DELETE route is mapped for these entities → router rejects
            // with 404 (no route at all) or 405 (route exists for other HTTP
            // verbs), so historical rows can never be hard-deleted.
            $response = $this->deleteJson($url);
            $this->assertContains(
                $response->status(),
                [404, 405],
                "DELETE {$url} must be rejected (got {$response->status()} instead)."
            );
        }
    }

    public function test_owner_and_user_cannot_hard_delete_financial_rows_either(): void
    {
        foreach ([UserRole::OWNER, UserRole::USER] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            Sanctum::actingAs($actor);

            $response = $this->deleteJson('/api/v1/payments/1');
            $this->assertContains($response->status(), [404, 405], 'payments delete must be rejected');

            $response = $this->deleteJson('/api/v1/refunds/1');
            $this->assertContains($response->status(), [404, 405], 'refunds delete must be rejected');

            $response = $this->deleteJson('/api/v1/invoices/1');
            $this->assertContains($response->status(), [404, 405], 'invoices delete must be rejected');
        }
    }

    public function test_bank_account_delete_is_safe_for_used_and_unused_accounts(): void
    {
        $admin = User::factory()->admin()->create();
        $used = BankAccount::factory()->create();
        $unused = BankAccount::factory()->create();

        Payment::factory()->create(['bank_account_id' => $used->id]);

        Sanctum::actingAs($admin);

        // Used account: 409, row survives, history intact
        $this->deleteJson("/api/v1/bank-accounts/{$used->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
        $this->assertDatabaseHas('bank_accounts', ['id' => $used->id]);
        $this->assertDatabaseHas('payments', ['bank_account_id' => $used->id]);

        // Unused account: 200 and gone
        $this->deleteJson("/api/v1/bank-accounts/{$unused->id}")->assertStatus(200);
        $this->assertDatabaseMissing('bank_accounts', ['id' => $unused->id]);
    }
}
