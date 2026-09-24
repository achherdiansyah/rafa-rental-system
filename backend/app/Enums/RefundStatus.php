<?php

namespace App\Enums;

enum RefundStatus: string
{
    case REQUESTED = 'REQUESTED';
    case REVIEWED = 'REVIEWED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
}
