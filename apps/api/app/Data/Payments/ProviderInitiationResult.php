<?php

namespace App\Data\Payments;

use App\Enums\ProviderInitiationStatus;

final readonly class ProviderInitiationResult
{
    public function __construct(
        public ProviderInitiationStatus $status,
        public ?string $operationReference = null,
        public ?string $providerReference = null,
        public ?string $redirectUrl = null,
        public ?string $expiresAt = null,
        public ?string $providerStatus = null,
    ) {}
}
