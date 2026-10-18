<?php

namespace App\Console\Commands;

use App\Services\Billing\PaymentHoldService;
use Illuminate\Console\Command;

class EvaluatePaymentHoldsCommand extends Command
{
    protected $signature = 'rentals:evaluate-payment-holds';

    protected $description = 'Start PAYMENT HOLD for rentals with >=3 unpaid daily invoices or oldest unpaid invoice >=72h';

    public function __construct(
        private readonly PaymentHoldService $holds
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $created = $this->holds->evaluateActiveRentals();

        if ($created > 0) {
            $this->info("{$created} PAYMENT HOLD dibuat.");
        }

        return self::SUCCESS;
    }
}
