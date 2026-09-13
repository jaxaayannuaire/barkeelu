<?php

namespace App\Enums;

enum FeeType: string
{
    case PLATFORM_FEE = 'PLATFORM_FEE';
    case PAYOUT_PROVISION = 'PAYOUT_PROVISION';
    case PAYMENT_PROVIDER_FEE = 'PAYMENT_PROVIDER_FEE';
    case PAYOUT_PROVIDER_FEE = 'PAYOUT_PROVIDER_FEE';
}
