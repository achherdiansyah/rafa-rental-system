<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case OVERPAID = 'OVERPAID';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
