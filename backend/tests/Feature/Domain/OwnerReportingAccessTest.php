<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner read/monitoring on reporting; mutation stays forbidden.
 */
class OwnerReportingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_reads_executive_and_financial_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();

        Sanctum::actingAs($owner);

        // Executive dashboard bundle
        $this->getJson('/api/v1/reports/dashboard')->assertOk();

        // Revenue report detail endpoints
        $this->getJson('/api/v1/reports/financial/invoices')->assertOk();
        $this->getJson('/api/v1/reports/financial/payments')->assertOk();
        $this->getJson('/api/v1/reports/financial/partials')->assertOk();
        $this->getJson('/api/v1/reports/financial/outstanding')->assertOk();
        $this->getJson('/api/v1/reports/financial/overpayments')->assertOk();
        $this->getJson('/api/v1/reports/financial/refunds')->assertOk();
    }

    public function test_regular_user_blocked_from_executive_dashboard(): void
    {
        $user = User::factory()->owner()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/dashboard')->assertStatus(403);
    }
}
