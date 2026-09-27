<?php

namespace App\Enums;

enum RefundSource: string
{
    case CANCELLATION = 'CANCELLATION';
    case OVERPAYMENT = 'OVERPAYMENT';
}
