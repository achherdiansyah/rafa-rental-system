<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case ASSIGNED = 'ASSIGNED';
    case REPLACED = 'REPLACED';
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';
}
