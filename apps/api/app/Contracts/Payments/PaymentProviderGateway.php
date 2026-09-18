<?php

namespace App\Contracts\Payments;

use App\Data\Payments\ProviderInitiationResult;
use App\Data\Payments\ProviderStatusResult;
use App\Models\Payment;
use App\Models\ProviderAccount;

interface PaymentProviderGateway
{
    public function availability(): bool;

    public function initiate(Payment $payment, array $context): ProviderInitiationResult;

    public function retrieve(Payment $payment): ProviderStatusResult;

    public function verifyWebhook(string $rawBody, array $headers): bool;

    public function mapWebhook(array $payload, ProviderAccount $account): array;
}
