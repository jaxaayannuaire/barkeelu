<?php

namespace App\Enums;

enum OrganizationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case ARCHIVED = 'ARCHIVED';
}
