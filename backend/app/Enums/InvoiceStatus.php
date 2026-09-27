<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'DRAFT';
    case ISSUED = 'ISSUED';
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case OVERPAID = 'OVERPAID';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';
}
