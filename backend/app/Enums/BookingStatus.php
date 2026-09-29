<?php

namespace App\Enums;

enum BookingStatus: string
{
    case DRAFT = 'DRAFT';
    // Status fase "diajukan menunggu antrean" dipersentasikan SEBAGAI PENDING_APPROVAL
    // (T-B01 state machine: DRAFT -> PENDING_APPROVAL; label SUBMITTED = alias fase, bukan state persistable).
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case REJECTED = 'REJECTED';
    case APPROVED = 'APPROVED';
    case PAYMENT_PENDING = 'PAYMENT_PENDING';
    case CONFIRMED = 'CONFIRMED';
    case DISPATCHED = 'DISPATCHED';
    case ARRIVED = 'ARRIVED';
    case ONGOING = 'ONGOING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
