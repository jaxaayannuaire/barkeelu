<?php

namespace App\Enums;

enum KycRiskLevel: string
{
    case UNKNOWN = 'UNKNOWN';
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
}
