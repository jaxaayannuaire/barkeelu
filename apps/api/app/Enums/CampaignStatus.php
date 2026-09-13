<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case REJECTED = 'REJECTED';
    case PUBLISHED = 'PUBLISHED';
    case PAUSED = 'PAUSED';
    case SUSPENDED = 'SUSPENDED';
    case ENDED = 'ENDED';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';
}
