<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Availability Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Operational buffers applied to every committed rental slot when computing
    | availability. All values are full calendar days, configurable per deployment.
    | These buffers are applied symmetrically to both the requested window and
    | committed slots when checking for overlap (prevents double-booking margin).
    |
    */

    'buffers' => [
        // Buffer before rental start (mobilization / preparation / travel to site)
        'mobilization_days' => 0,

        // Post-end extension/demobilization grace buffer
        'extension_days' => 3,

        // Return & inspection buffer after demobilization
        'inspection_days' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Committed Booking Statuses
    |--------------------------------------------------------------------------
    | Statuses that consume inventory capacity. EXPIRED / CANCELLED / REJECTED
    | release capacity back to the public pool immediately.
    */
    'committed_statuses' => [
        'APPROVED',
        'PAYMENT_PENDING',
        'CONFIRMED',
        'DISPATCHED',
        'ARRIVED',
        'ONGOING',
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry & Payment Deadline
    |--------------------------------------------------------------------------
    | payment_deadline = approval time (invoice issued_at boundary) + grace hours.
    | invoice/payment engine lands in Phase 10; this is the integration seam.
    */
    'payment_grace_hours' => 24,

    'payment_pending_statuses' => [
        'APPROVED',
        'PAYMENT_PENDING',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unavailable Physical Unit Statuses
    |--------------------------------------------------------------------------
    | Units in these states can never be allocated to a new rental.
    */
    'unavailable_unit_statuses' => [
        'DECOMMISSIONED',
        'MAINTENANCE',
        'RETURN_INSPECTION',
    ],
];
