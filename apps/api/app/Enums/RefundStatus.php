<?php

namespace App\Enums;

enum RefundStatus: string
{
    case REQUESTED = 'REQUESTED';
    case APPROVED = 'APPROVED';
    case PROCESSING = 'PROCESSING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case UNKNOWN = 'UNKNOWN';
    case CANCELLED = 'CANCELLED';
}
