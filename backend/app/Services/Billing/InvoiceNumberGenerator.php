<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential invoice numbers in the mandated format INV/YYYYMM/XXXX.
 */
class InvoiceNumberGenerator
{
    public function generate(): string
    {
        $prefix = 'INV/'.now()->format('Ym').'/';

        return DB::transaction(function () use ($prefix) {
            $last = Invoice::withTrashed()
                ->where('invoice_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->value('invoice_number');

            $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
