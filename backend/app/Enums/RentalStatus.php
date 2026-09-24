<?php

namespace App\Enums;

enum RentalStatus: string
{
    case PENDING_ASSIGNMENT = 'PENDING_ASSIGNMENT';
    case ASSIGNED = 'ASSIGNED';
    case DISPATCHED = 'DISPATCHED';
    case ARRIVED = 'ARRIVED';
    case ONGOING = 'ONGOING';
    case DEMOBILIZING = 'DEMOBILIZING';
    case RETURN_INSPECTED = 'RETURN_INSPECTED';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
