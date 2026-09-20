<?php

namespace App\Support;

final class FeeLabel
{
    public static function for(?string $feeType): string
    {
        return match ($feeType) {
            'PLATFORM_FEE' => 'Frais de plateforme',
            'PAYOUT_PROVISION' => 'Provision de transfert',
            default => 'Frais applicables',
        };
    }
}
