<?php

namespace App\Enums;

enum OrganizationMembershipRole: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case MEMBER = 'MEMBER';
}
