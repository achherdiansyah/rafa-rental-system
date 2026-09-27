<?php

namespace App\Actions\Billing;

use App\DTOs\Billing\RentalBill;
use App\Models\Rental;
use App\Models\User;
use App\Services\Billing\RentalBillingService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\Gate;

/**
 * Orchestrates bill generation for a rental. Billing logic itself lives in
 * RentalBillingService; this action only authorises, runs and audits.
 */
class GenerateRentalBillAction
{
    public function __construct(
        private readonly RentalBillingService $billing
    ) {}

    public function execute(User $actor, Rental $rental): RentalBill
    {
        Gate::authorize('view', $rental);

        $bill = $this->billing->generate($rental);

        AuditLogger::log('RENTAL_BILL_GENERATED', $rental, [], [
            'total_work_hours' => $bill->totalWorkHours,
            'total_rental' => $bill->totalRentalAmount,
            'total_mob' => $bill->totalMobAmount,
            'total_demob' => $bill->totalDemobAmount,
            'grand_total' => $bill->grandTotal,
        ]);

        return $bill;
    }
}
