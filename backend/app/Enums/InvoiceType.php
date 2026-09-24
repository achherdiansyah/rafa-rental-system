<?php

namespace App\Enums;

enum InvoiceType: string
{
    case RENTAL = 'RENTAL';
    case MOB_DEMOB = 'MOB_DEMOB';
    case ADDITIONAL_CHARGE = 'ADDITIONAL_CHARGE';
    case PENALTY = 'PENALTY';
    case DAMAGE = 'DAMAGE';
}
