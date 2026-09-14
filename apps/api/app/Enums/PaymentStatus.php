<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case CREATED = 'CREATED';
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PAID = 'PAID';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
    case UNKNOWN = 'UNKNOWN';
}
