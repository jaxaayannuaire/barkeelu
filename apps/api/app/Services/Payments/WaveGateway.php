<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProviderGateway;
use App\Data\Payments\ProviderInitiationResult;
use App\Data\Payments\ProviderStatusResult;
use App\Enums\ProviderInitiationStatus;
use App\Exceptions\Payments\AmbiguousProviderInitiationException;
use App\Models\Payment;
use App\Models\ProviderAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use RuntimeException;

class WaveGateway implements PaymentProviderGateway
{
    public function __construct(
        private WaveCheckoutService $checkout,
        private WaveWebhookMapper $webhooks,
    ) {}

    public function availability(): bool
    {
        return $this->checkout->available();
    }

    public function initiate(Payment $payment, array $context): ProviderInitiationResult
    {
        try {
            $response = $this->checkout->initiate(
                $payment,
                $context['payer_mobile'],
                $context['success_url'],
                $context['error_url'],
            );
        } catch (ConnectionException) {
            return new ProviderInitiationResult(ProviderInitiationStatus::SENT_UNKNOWN);
        } catch (AmbiguousProviderInitiationException) {
            return new ProviderInitiationResult(ProviderInitiationStatus::SENT_UNKNOWN);
        } catch (RuntimeException) {
            return new ProviderInitiationResult(ProviderInitiationStatus::NOT_SENT);
        }

        return new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            (string) $response['id'],
            $payment->internal_reference,
            $response['wave_launch_url'],
            isset($response['when_expires']) ? Carbon::parse($response['when_expires'])->toIso8601String() : null,
            'OPEN',
        );
    }

    public function retrieve(Payment $payment): ProviderStatusResult
    {
        if (filled($payment->provider_checkout_session_id)) {
            return $this->statusResult($this->checkout->retrieve($payment->provider_checkout_session_id), $payment);
        }

        if (! filled($payment->provider_client_reference)
            || ! hash_equals($payment->internal_reference, $payment->provider_client_reference)) {
            return new ProviderStatusResult('UNKNOWN');
        }

        $results = $this->checkout->searchByClientReference($payment->provider_client_reference)['result'] ?? null;
        if (! is_array($results) || count($results) !== 1) {
            return new ProviderStatusResult('UNKNOWN');
        }

        $response = $results[0];
        if (! is_array($response)
            || ! is_string($response['id'] ?? null)
            || ! is_string($response['client_reference'] ?? null)
            || ! hash_equals($payment->provider_client_reference, $response['client_reference'])
            || (string) $payment->amount !== (string) ($response['amount'] ?? '')
            || $payment->currency !== ($response['currency'] ?? null)) {
            return new ProviderStatusResult('UNKNOWN');
        }

        return $this->statusResult($response, $payment);
    }

    private function statusResult(array $response, Payment $payment): ProviderStatusResult
    {
        $checkout = $response['checkout_status'] ?? null;
        $providerStatus = $response['payment_status'] ?? null;
        $status = match (true) {
            $checkout === 'complete' && $providerStatus === 'succeeded' => 'PAID',
            $checkout === 'expired' || $providerStatus === 'failed' || $providerStatus === 'cancelled' => 'FAILED',
            $checkout === 'open' || $providerStatus === 'pending' || $providerStatus === 'processing' => 'PENDING',
            default => 'UNKNOWN',
        };

        return new ProviderStatusResult(
            $status,
            $response['id'] ?? $payment->provider_checkout_session_id,
            $response['transaction_id'] ?? null,
            $response['client_reference'] ?? $payment->provider_client_reference,
        );
    }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $signature = $headers['Wave-Signature'][0] ?? $headers['wave-signature'][0] ?? null;

        return $this->checkout->signatureIsValid($rawBody, $signature);
    }

    public function mapWebhook(array $payload, ProviderAccount $account): array
    {
        return $this->webhooks->map($payload, $account->id);
    }
}
