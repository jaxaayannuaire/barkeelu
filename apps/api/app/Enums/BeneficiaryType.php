<?php

namespace App\Enums;

enum BeneficiaryType: string
{
    case INDIVIDUAL = 'INDIVIDUAL';
    case ORGANIZATION = 'ORGANIZATION';
    case COMMUNITY = 'COMMUNITY';
    case OTHER = 'OTHER';
}
