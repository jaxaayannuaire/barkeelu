<?php

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case LEFT = 'LEFT';
}
