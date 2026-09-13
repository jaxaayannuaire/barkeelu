<?php

namespace App\Enums;

enum CampaignFundraisingStatus: string
{
    case NOT_STARTED = 'NOT_STARTED';
    case OPEN = 'OPEN';
    case PAUSED = 'PAUSED';
    case CLOSED = 'CLOSED';
}
