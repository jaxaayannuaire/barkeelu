<?php

namespace App\Services\Checkout;

use App\Data\Payments\CheckoutPaymentResult;
use App\Enums\CheckoutStatus;
use App\Enums\PaymentStatus;
use App\Models\CheckoutSession;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ProviderGatewayResolver;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutPaymentService
{
    public function __construct(
        private PaymentService $payments,
        private ProviderGatewayResolver $gateways,
        private CheckoutStateService $states,
    ) {}

    public function initiate(CheckoutSession $session, ProviderAccount $account, array $input): CheckoutPaymentResult
    {
        $prepared = DB::transaction(function () use ($session, $account, $input): array {
            $locked = CheckoutSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($locked->donation_id === null) {
                throw new DomainException('Checkout sans Donation.');
            }

            $hash = $this->paymentContentHash($locked->donation_id, $account->id, $locked->total_payable_amount, $locked->currency, $input);
            $existing = Payment::query()->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw new DomainException('Conflit d’idempotence initiation Payment.');
                }
                if ($existing->status === PaymentStatus::UNKNOWN) {
                    throw new DomainException('Payment UNKNOWN : retrieve provider obligatoire.');
                }

                return [$locked, $existing, false];
            }

            if (! in_array($locked->status, [CheckoutStatus::CONFIRMED, CheckoutStatus::FAILED], true)) {
                throw new DomainException('Checkout non initiable dans cet état.');
            }
            if ($locked->status === CheckoutStatus::UNKNOWN) {
                throw new DomainException('Un checkout UNKNOWN doit être résolu avant tout retry.');
            }
            if ($locked->checkout_expires_at->isPast()) {
                throw new DomainException('Checkout expiré.');
            }

            $payment = $this->payments->create($locked->donation, $account, [
                'amount' => $locked->total_payable_amount,
                'currency' => $locked->currency,
                'idempotency_key' => $input['idempotency_key'],
                'content_hash' => $hash,
            ]);
            $locked->update(['last_payment_id' => $payment->id]);

            return [$locked->refresh(), $payment->refresh(), true];
        }, 3);

        [$locked, $payment, $shouldInitiate] = $prepared;
        if (! $shouldInitiate || $payment->status !== PaymentStatus::CREATED) {
            return new CheckoutPaymentResult($payment);
        }

        $gateway = $this->gateways->for($account);
        $result = $gateway->initiate($payment, [
            'payer_mobile' => $input['payer_mobile'],
            'success_url' => $input['success_url'],
            'error_url' => $input['error_url'],
        ]);

        $updatedPayment = DB::transaction(function () use ($payment, $locked, $result, $input): Payment {
            $session = CheckoutSession::query()->lockForUpdate()->findOrFail($locked->id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($result->status->value === 'SENT_CONFIRMED') {
                $payment->update([
                    'status' => PaymentStatus::PENDING,
                    'provider_checkout_session_id' => $result->operationReference,
                    'provider_client_reference' => $result->providerReference,
                    'provider_checkout_expires_at' => $result->expiresAt,
                    'provider_status' => $result->providerStatus,
                    'payer_mobile_encrypted' => $input['payer_mobile'],
                ]);
                $this->transitionIfNeeded($session, CheckoutStatus::PAYMENT_PENDING);
            } elseif ($result->status->value === 'SENT_UNKNOWN') {
                $payment->update(['status' => PaymentStatus::UNKNOWN, 'provider_status' => 'UNKNOWN_SENT']);
                $this->transitionIfNeeded($session, CheckoutStatus::UNKNOWN, true);
            } else {
                $payment->update(['status' => PaymentStatus::FAILED, 'provider_status' => 'NOT_SENT']);
            }

            return $payment->refresh();
        });

        return new CheckoutPaymentResult($updatedPayment, $result->redirectUrl);
    }

    public function resolveUnknown(Payment $payment): Payment
    {
        if ($payment->status !== PaymentStatus::UNKNOWN) {
            throw new DomainException('Seul un Payment UNKNOWN peut être résolu.');
        }

        $gateway = $this->gateways->for($payment->providerAccount);
        $result = $gateway->retrieve($payment);
        $resolved = $this->payments->applyProviderState($payment, [
            'provider_account_id' => $payment->provider_account_id,
            'internal_reference' => $payment->internal_reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider_status' => $result->status,
            'provider_payment_id' => $result->providerPaymentId,
            'provider_reference' => $result->providerReference,
        ]);
        $this->syncFromPayment($resolved);

        return $resolved;
    }

    public function syncFromPayment(Payment $payment): void
    {
        $session = CheckoutSession::query()->where('last_payment_id', $payment->id)->first();
        if ($session === null) {
            return;
        }

        $target = match ($payment->status) {
            PaymentStatus::PENDING, PaymentStatus::PROCESSING => CheckoutStatus::PAYMENT_PENDING,
            PaymentStatus::UNKNOWN => CheckoutStatus::UNKNOWN,
            PaymentStatus::PAID => CheckoutStatus::PAID,
            PaymentStatus::FAILED, PaymentStatus::CANCELLED, PaymentStatus::EXPIRED => CheckoutStatus::FAILED,
            default => null,
        };
        if ($target === null || $session->status === $target) {
            return;
        }
        if ($target === CheckoutStatus::PAID) {
            if ($session->status === CheckoutStatus::CONFIRMED) {
                $this->states->transition($session, CheckoutStatus::PAYMENT_PENDING, true);
                $session->refresh();
            }
            $this->states->transition($session->refresh(), $target, true);
        } elseif ($target === CheckoutStatus::UNKNOWN) {
            $this->states->transition($session, $target, true, true);
        } elseif (in_array($session->status, [CheckoutStatus::PAYMENT_PENDING, CheckoutStatus::UNKNOWN], true)) {
            $this->states->transition($session, $target, true);
        }
    }

    private function transitionIfNeeded(CheckoutSession $session, CheckoutStatus $target, bool $providerEvidence = false): void
    {
        if ($session->status !== $target) {
            $this->states->transition($session, $target, true, $providerEvidence);
        }
    }

    private function paymentContentHash(int $donationId, int $providerAccountId, int $amount, string $currency, array $input): string
    {
        return hash('sha256', json_encode([
            'donation_id' => $donationId,
            'provider_account_id' => $providerAccountId,
            'amount' => $amount,
            'currency' => $currency,
            'payer_mobile' => $input['payer_mobile'],
            'success_url' => $input['success_url'],
            'error_url' => $input['error_url'],
        ], JSON_THROW_ON_ERROR));
    }
}
