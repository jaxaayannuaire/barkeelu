<?php

namespace App\Enums;

enum CheckoutStatus: string
{
    case DRAFT = 'DRAFT';
    case QUOTED = 'QUOTED';
    case CONFIRMED = 'CONFIRMED';
    case PAYMENT_PENDING = 'PAYMENT_PENDING';
    case PAID = 'PAID';
    case FAILED = 'FAILED';
    case UNKNOWN = 'UNKNOWN';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
