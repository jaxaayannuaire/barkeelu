<?php

namespace App\Enums;

enum BeneficiaryRepresentativeStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ENDED = 'ENDED';
    case REVOKED = 'REVOKED';
}
