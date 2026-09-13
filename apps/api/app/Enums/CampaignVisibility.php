<?php

namespace App\Enums;

enum CampaignVisibility: string
{
    case PUBLIC = 'PUBLIC';
    case UNLISTED = 'UNLISTED';
    case PRIVATE = 'PRIVATE';
    case TARGETED = 'TARGETED';
}
