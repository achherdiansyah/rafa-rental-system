<?php

return [
    // Work stops when either threshold is reached (whichever comes first).
    'max_unpaid_daily_invoices' => (int) env('PAYMENT_HOLD_MAX_INVOICES', 3),
    'max_oldest_unpaid_hours' => (int) env('PAYMENT_HOLD_MAX_HOURS', 72),
];
