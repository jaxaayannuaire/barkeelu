<?php

namespace App\Enums;

enum KycStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case SUSPENDED = 'SUSPENDED';
}
