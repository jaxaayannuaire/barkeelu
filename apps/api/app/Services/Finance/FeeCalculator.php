<?php

namespace App\Services\Finance;

use InvalidArgumentException;

class FeeCalculator
{
    public function calculate(int $basis, int $bps, int $fixed = 0): int
    {
        if ($basis < 0 || $bps < 0 || $fixed < 0) {
            throw new InvalidArgumentException('Montant, taux et montant fixe doivent être positifs ou nuls.');
        }

        // Arrondi arithmétique entier au demi supérieur : (n + 0,5) / 10 000.
        return intdiv(($basis * $bps) + 5000, 10000) + $fixed;
    }
}
