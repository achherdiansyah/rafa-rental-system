<?php

namespace App\Enums;

enum InvoiceType: string
{
    case RENTAL_PREPAYMENT = 'RENTAL_PREPAYMENT';
    case DAILY_WORK = 'DAILY_WORK';
    case MOB_DEMOB = 'MOB_DEMOB';
    case ADJUSTMENT = 'ADJUSTMENT';
    case OTHER = 'OTHER';
}
