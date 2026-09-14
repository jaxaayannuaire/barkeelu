<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case REQUESTED = 'REQUESTED';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case RESERVED = 'RESERVED';
    case PROCESSING = 'PROCESSING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
    case UNKNOWN = 'UNKNOWN';
    case CANCELLED = 'CANCELLED';
    case BLOCKED = 'BLOCKED';
}
