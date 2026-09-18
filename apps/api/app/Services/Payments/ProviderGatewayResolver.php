<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderGateway;
use App\Models\ProviderAccount;
use DomainException;

class ProviderGatewayResolver
{
    public function __construct(private WaveGateway $wave) {}

    public function for(ProviderAccount $account): PaymentProviderGateway
    {
        if ($account->provider === 'WAVE') {
            return $this->wave;
        }

        throw new DomainException('Aucun adaptateur provider disponible pour ce compte.');
    }
}
