<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RefundPolicy;
use App\Policies\TimesheetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function actingRole(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_phase1_capability_matrix_per_role(): void
    {
        $user = $this->actingRole(UserRole::USER->value);
        $admin = $this->actingRole(UserRole::ADMIN->value);
        $owner = $this->actingRole(UserRole::OWNER->value);

        // [USER, ADMIN, OWNER]
        $matrix = [
            'view-admin-dashboard' => [false, true, true],
            'manage-equipment' => [false, true, true],
            'approve-booking' => [false, true, true],
            'verify-payment' => [false, true, true],
            'manage-bank-accounts' => [false, true, true],

            'assign-units' => [false, true, false],
            'dispatch-unit' => [false, true, false],
            'validate-bast' => [false, true, false],
            'validate-timesheet' => [false, true, false],
            'process-refund' => [false, true, false],

            'view-owner-dashboard' => [false, false, true],
            'view-revenue-reports' => [false, false, true],
            'view-audit-logs' => [false, false, true],
            'manage-pricing-master' => [false, false, true],
            'approve-refund' => [false, false, true],
            'decommission-equipment' => [false, false, true],
            'deactivate-user' => [false, false, true],
        ];

        foreach ($matrix as $gate => [$userOk, $adminOk, $ownerOk]) {
            $this->assertSame($userOk, Gate::forUser($user)->allows($gate), "Gate {$gate} utk USER");
            $this->assertSame($adminOk, Gate::forUser($admin)->allows($gate), "Gate {$gate} utk ADMIN");
            $this->assertSame($ownerOk, Gate::forUser($owner)->allows($gate), "Gate {$gate} utk OWNER");
        }
    }

    public function test_financial_policy_role_boundaries(): void
    {
        $user = $this->actingRole(UserRole::USER->value);
        $admin = $this->actingRole(UserRole::ADMIN->value);
        $owner = $this->actingRole(UserRole::OWNER->value);

        // Invoice lifecycle management => ADMIN only
        $this->assertFalse((new InvoicePolicy)->manage($owner));
        $this->assertTrue((new InvoicePolicy)->manage($admin));
        $this->assertFalse((new InvoicePolicy)->manage($user));

        // Refund: approve => OWNER only; manage => ADMIN only
        $this->assertTrue((new RefundPolicy)->approve($owner));
        $this->assertFalse((new RefundPolicy)->approve($admin));
        $this->assertFalse((new RefundPolicy)->manage($owner));
        $this->assertTrue((new RefundPolicy)->manage($admin));

        // Payment verification => ADMIN only
        $this->assertTrue((new PaymentPolicy)->manage($admin));
        $this->assertFalse((new PaymentPolicy)->manage($owner));

        // Timesheet validation => ADMIN only
        $this->assertTrue((new TimesheetPolicy)->validate($admin));
        $this->assertFalse((new TimesheetPolicy)->validate($owner));
        $this->assertFalse((new TimesheetPolicy)->validate($user));
    }
}
