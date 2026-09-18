<?php

namespace App\Data\Payments;

final readonly class ProviderStatusResult
{
    public function __construct(
        public string $status,
        public ?string $operationReference = null,
        public ?string $providerPaymentId = null,
        public ?string $providerReference = null,
    ) {}
}
