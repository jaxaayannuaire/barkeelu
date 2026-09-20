<?php

namespace App\Services\Payments;

use App\Models\Payment;
use DomainException;

class WaveWebhookMapper
{
    public function map(array $payload, int $providerAccountId): array
    {
        if (($payload['type'] ?? null) === 'test.test_event') {
            return ['ignore' => true];
        }

        $data = $payload['data'] ?? null;
        if (! is_array($data) || ! is_string($payload['id'] ?? null) || ! is_string($payload['type'] ?? null)) {
            throw new DomainException('Webhook Wave incomplet.');
        }
        if ($payload['type'] === 'checkout.session.payment_failed') {
            $payment = Payment::query()->where('provider_account_id', $providerAccountId)
                ->where('provider_checkout_session_id', $data['id'] ?? null)->firstOrFail();

            return ['payment' => $payment, 'event' => [
                'provider_account_id' => $providerAccountId, 'internal_reference' => $payment->internal_reference,
                'amount' => $payment->amount, 'currency' => $payment->currency, 'provider_status' => 'FAILED',
                'provider_payment_id' => $data['transaction_id'] ?? null, 'provider_reference' => $payment->provider_client_reference,
                'payload' => ['event_id' => $payload['id'], 'type' => $payload['type'], 'checkout_status' => $data['checkout_status'] ?? null, 'payment_status' => $data['payment_status'] ?? null],
            ]];
        }
        $payment = Payment::query()->where('provider_account_id', $providerAccountId)
            ->where('provider_checkout_session_id', $data['id'] ?? null)
            ->where('provider_client_reference', $data['client_reference'] ?? null)->firstOrFail();
        if (($data['amount'] ?? null) !== (string) $payment->amount || ($data['currency'] ?? null) !== $payment->currency) {
            throw new DomainException('Webhook Wave incohérent.');
        }
        $checkout = $data['checkout_status'] ?? null;
        $status = $data['payment_status'] ?? null;
        $providerStatus = match (true) {
            $checkout === 'complete' && $status === 'succeeded' => 'PAID',
            $checkout === 'open' => 'PENDING',
            $checkout === 'expired' => 'EXPIRED',
            default => 'UNKNOWN',
        };

        return ['payment' => $payment, 'event' => [
            'provider_account_id' => $providerAccountId, 'internal_reference' => $payment->internal_reference,
            'amount' => $payment->amount, 'currency' => $payment->currency, 'provider_status' => $providerStatus,
            'provider_payment_id' => $data['transaction_id'] ?? null, 'provider_reference' => $data['client_reference'],
            'payload' => ['event_id' => $payload['id'], 'type' => $payload['type'], 'checkout_status' => $checkout, 'payment_status' => $status],
        ]];
    }
}
