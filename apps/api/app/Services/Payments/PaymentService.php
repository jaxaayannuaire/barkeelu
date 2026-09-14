<?php

namespace App\Services\Payments;

use App\Enums\DonationStatus;
use App\Enums\PaymentStatus;
use App\Models\Donation;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Services\Finance\LedgerPostingService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(private LedgerPostingService $ledgerPostingService) {}

    public function create(Donation $donation, ProviderAccount $providerAccount, array $input): Payment
    {
        if (! $providerAccount->is_active || $providerAccount->currency !== $donation->currency || $input['amount'] !== $donation->total_payable_amount) {
            throw new DomainException('Tentative de paiement incompatible avec la donation.');
        }

        $payload = [
            'donation_id' => $donation->id,
            'provider_account_id' => $providerAccount->id,
            'amount' => $input['amount'],
            'currency' => $input['currency'],
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($donation, $providerAccount, $input, $hash): Payment {
            $existing = Payment::query()->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();

            if ($existing !== null) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw new DomainException('Conflit d’idempotence payment.');
                }

                return $existing;
            }

            return Payment::query()->create([
                'public_id' => (string) Str::uuid(),
                'donation_id' => $donation->id,
                'provider_account_id' => $providerAccount->id,
                'provider' => $providerAccount->provider,
                'internal_reference' => 'pay_'.Str::uuid(),
                'currency' => $input['currency'],
                'amount' => $input['amount'],
                'status' => PaymentStatus::CREATED,
                'idempotency_key' => $input['idempotency_key'],
                'content_hash' => $hash,
            ]);
        });
    }

    public function applyProviderState(Payment $payment, array $event): Payment
    {
        return DB::transaction(function () use ($payment, $event): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::PAID) {
                return $payment;
            }

            $this->verifyEvent($payment, $event);
            $state = $event['provider_status'];

            if ($state === 'TIMEOUT') {
                $payment->update(['status' => PaymentStatus::UNKNOWN, 'provider_status' => $state]);

                return $payment->refresh();
            }

            if ($state !== 'PAID') {
                $target = match ($state) {
                    'PENDING' => PaymentStatus::PENDING,
                    'PROCESSING' => PaymentStatus::PROCESSING,
                    'CANCELLED' => PaymentStatus::CANCELLED,
                    'EXPIRED' => PaymentStatus::EXPIRED,
                    'FAILED' => PaymentStatus::FAILED,
                    default => PaymentStatus::UNKNOWN,
                };
                $payment->update(['status' => $target, 'provider_status' => $state]);

                return $payment->refresh();
            }

            $donation = Donation::query()->lockForUpdate()->findOrFail($payment->donation_id);
            $firstSuccess = ! Payment::query()
                ->where('donation_id', $donation->id)
                ->where('status', PaymentStatus::PAID)
                ->exists();

            $payment->update([
                'status' => PaymentStatus::PAID,
                'provider_status' => $state,
                'provider_payment_id' => $event['provider_payment_id'],
                'provider_reference' => $event['provider_reference'] ?? null,
                'provider_payload' => $event['payload'] ?? null,
                'paid_at' => now(),
            ]);

            $this->postLedger($payment->refresh(), $donation, $firstSuccess);

            if ($firstSuccess) {
                $donation->update(['status' => DonationStatus::PAID, 'paid_at' => now()]);
            }

            return $payment->refresh();
        });
    }

    private function verifyEvent(Payment $payment, array $event): void
    {
        if ($event['provider_account_id'] !== $payment->provider_account_id
            || $event['internal_reference'] !== $payment->internal_reference
            || $event['amount'] !== $payment->amount
            || $event['currency'] !== $payment->currency) {
            throw new DomainException('Événement fournisseur incohérent.');
        }
    }

    private function postLedger(Payment $payment, Donation $donation, bool $firstSuccess): void
    {
        $accounts = LedgerAccount::query()
            ->whereIn('code', $firstSuccess
                ? ['PAYMENT_CLEARING', 'CAMPAIGN_PAYABLE', 'PLATFORM_FEE_REVENUE', 'PAYOUT_PROVISION_RESERVE']
                : ['PAYMENT_CLEARING', 'UNAPPLIED_FUNDS'])
            ->get()
            ->keyBy('code');

        $entries = [[
            'account_id' => $accounts['PAYMENT_CLEARING']->id,
            'campaign_id' => $donation->campaign_id,
            'direction' => 'DEBIT',
            'amount' => $payment->amount,
        ]];

        if ($firstSuccess) {
            $entries[] = ['account_id' => $accounts['CAMPAIGN_PAYABLE']->id, 'campaign_id' => $donation->campaign_id, 'direction' => 'CREDIT', 'amount' => $donation->nominal_amount];
            if ($donation->platform_fee_amount > 0) {
                $entries[] = ['account_id' => $accounts['PLATFORM_FEE_REVENUE']->id, 'direction' => 'CREDIT', 'amount' => $donation->platform_fee_amount];
            }
            if ($donation->payout_provision_amount > 0) {
                $entries[] = ['account_id' => $accounts['PAYOUT_PROVISION_RESERVE']->id, 'direction' => 'CREDIT', 'amount' => $donation->payout_provision_amount];
            }
        } else {
            $entries[] = ['account_id' => $accounts['UNAPPLIED_FUNDS']->id, 'direction' => 'CREDIT', 'amount' => $payment->amount];
        }

        $this->ledgerPostingService->post(
            'payment:'.$payment->public_id.':'.($firstSuccess ? 'captured' : 'unapplied'),
            $payment->currency,
            $entries,
            $firstSuccess ? 'Payment captured' : 'Additional payment unapplied',
        );
    }
}
