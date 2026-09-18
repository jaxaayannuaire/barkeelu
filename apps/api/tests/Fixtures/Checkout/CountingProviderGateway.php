<?php

namespace Tests\Fixtures\Checkout;

use App\Contracts\Payments\PaymentProviderGateway;
use App\Data\Payments\ProviderInitiationResult;
use App\Data\Payments\ProviderStatusResult;
use App\Enums\ProviderInitiationStatus;
use App\Models\Payment;
use App\Models\ProviderAccount;
use Illuminate\Support\Facades\DB;

class CountingProviderGateway implements PaymentProviderGateway
{
    public function availability(): bool
    {
        return true;
    }

    public function initiate(Payment $payment, array $context): ProviderInitiationResult
    {
        DB::table('checkout_test_provider_calls')->insert([
            'payment_id' => $payment->id,
            'created_at' => now(),
        ]);

        return new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            'concurrent-provider-session-'.$payment->id,
            $payment->internal_reference,
            'https://provider.test/'.$payment->id,
        );
    }

    public function retrieve(Payment $payment): ProviderStatusResult
    {
        return new ProviderStatusResult('PENDING', null, null, $payment->provider_client_reference);
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        return true;
    }

    public function mapWebhook(array $payload, ProviderAccount $account): array
    {
        return [];
    }
}
