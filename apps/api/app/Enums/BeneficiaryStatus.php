<?php

namespace App\Enums;

enum BeneficiaryStatus: string
{
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case ARCHIVED = 'ARCHIVED';
}
