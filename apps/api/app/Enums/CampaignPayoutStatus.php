<?php

namespace App\Enums;

enum CampaignPayoutStatus: string
{
    case NOT_ELIGIBLE = 'NOT_ELIGIBLE';
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case ELIGIBLE = 'ELIGIBLE';
    case RESERVED = 'RESERVED';
    case PROCESSING = 'PROCESSING';
    case BLOCKED = 'BLOCKED';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case COMPLETED = 'COMPLETED';
}
