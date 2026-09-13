<?php

namespace App\Enums;

enum OrganizationType: string
{
    case NGO = 'NGO';
    case ASSOCIATION = 'ASSOCIATION';
    case DAHIRA = 'DAHIRA';
    case FOUNDATION = 'FOUNDATION';
    case COMPANY = 'COMPANY';
    case RSE_NETWORK = 'RSE_NETWORK';
    case MOSQUE = 'MOSQUE';
    case CHURCH = 'CHURCH';
    case HOSPITAL = 'HOSPITAL';
    case SCHOOL = 'SCHOOL';
    case GOVERNMENT = 'GOVERNMENT';
    case SOCIAL_SERVICE = 'SOCIAL_SERVICE';
    case COMMUNITY_GROUP = 'COMMUNITY_GROUP';
    case OTHER = 'OTHER';
}
