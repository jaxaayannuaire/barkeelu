<?php

namespace App\Enums;

enum DonationStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';
    case FAILED = 'FAILED';
}
