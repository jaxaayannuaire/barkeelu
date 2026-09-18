<?php

namespace App\Services\Checkout;

use App\Enums\CheckoutStatus;
use App\Enums\DonationStatus;
use App\Enums\FeeType;
use App\Models\CheckoutSession;
use App\Models\Donation;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutConfirmationService
{
    /**
     * Flux 07A2 dédié au snapshot confirmé : DonationService legacy recalcule
     * les politiques et matérialise des AppliedFee, donc reste inchangé dans
     * le périmètre parallèle existant.
     */
    public function confirm(CheckoutSession $session, array $input): CheckoutSession
    {
        return DB::transaction(function () use ($session, $input): CheckoutSession {
            $locked = CheckoutSession::query()->lockForUpdate()->findOrFail($session->id);
            $now = now();

            if ($locked->status === CheckoutStatus::CONFIRMED) {
                $this->assertConfirmationIdempotence($locked, $input);

                return $locked->refresh();
            }

            if ($locked->status !== CheckoutStatus::QUOTED) {
                throw new DomainException('Seule une quote peut être confirmée.');
            }
            if ($locked->quote_expires_at === null || ! $locked->quote_expires_at->isFuture()) {
                throw new DomainException('Quote expirée.');
            }
            if ($locked->checkout_expires_at->isPast()) {
                throw new DomainException('Checkout expiré.');
            }

            $this->assertSnapshotCoherent($locked);
            $confirmationHash = $this->confirmationContentHash($locked);

            $donationKey = 'checkout-confirmation:'.$locked->public_id;
            $donation = Donation::query()->where('idempotency_key', $donationKey)->lockForUpdate()->first();
            if ($donation === null) {
                $fees = collect($locked->fee_snapshot['fees']);
                $platformFee = (int) $fees->where('fee_type', FeeType::PLATFORM_FEE->value)->sum('calculated_amount');
                $payoutProvision = (int) $fees->where('fee_type', FeeType::PAYOUT_PROVISION->value)->sum('calculated_amount');
                $donor = $locked->donor_snapshot;

                $donation = Donation::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'campaign_id' => $locked->campaign_id,
                    'donor_user_id' => $locked->donor_user_id,
                    'donor_name' => $donor['name'],
                    'donor_email' => $donor['email'],
                    'is_anonymous' => $donor['is_anonymous'],
                    'currency' => $locked->currency,
                    'nominal_amount' => $locked->nominal_amount,
                    'platform_fee_amount' => $platformFee,
                    'payout_provision_amount' => $payoutProvision,
                    'total_payable_amount' => $locked->total_payable_amount,
                    'status' => DonationStatus::PENDING,
                    'idempotency_key' => $donationKey,
                    'content_hash' => $confirmationHash,
                    'created_by_user_id' => $locked->donor_user_id,
                ]);
            } elseif (! hash_equals($donation->content_hash, $confirmationHash)) {
                throw new DomainException('Conflit de confirmation checkout.');
            }

            $locked->update([
                'donation_id' => $donation->id,
                'confirmed_at' => $now,
                'status' => CheckoutStatus::CONFIRMED,
                'fee_snapshot' => array_merge($locked->fee_snapshot, [
                    'confirmation' => [
                        'idempotency_key' => $input['idempotency_key'],
                        'content_hash' => $confirmationHash,
                    ],
                ]),
            ]);

            return $locked->refresh();
        });
    }

    private function assertConfirmationIdempotence(CheckoutSession $session, array $input): void
    {
        $confirmation = $session->fee_snapshot['confirmation'] ?? null;
        $currentHash = $this->confirmationContentHash($session);
        if ($confirmation === null
            || $confirmation['idempotency_key'] !== $input['idempotency_key']
            || ! hash_equals($confirmation['content_hash'], $currentHash)) {
            throw new DomainException('Conflit d’idempotence confirmation checkout.');
        }
    }

    private function confirmationContentHash(CheckoutSession $session): string
    {
        $feeSnapshot = $session->fee_snapshot;
        unset($feeSnapshot['confirmation']);
        unset($feeSnapshot['quote_history']);

        return hash('sha256', json_encode($this->canonicalize([
            'checkout_session_id' => $session->id,
            'fee_snapshot' => $feeSnapshot,
            'donor_snapshot' => $session->donor_snapshot,
        ]), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        $canonical = [];
        foreach ($value as $key => $item) {
            $canonical[(string) $key] = $this->canonicalize($item);
        }
        ksort($canonical, SORT_STRING);

        return $canonical;
    }

    private function assertSnapshotCoherent(CheckoutSession $session): void
    {
        $snapshot = $session->fee_snapshot;
        $donor = $session->donor_snapshot;
        if (! is_array($snapshot) || ! is_array($snapshot['fees'] ?? null) || ! is_array($donor)
            || $snapshot['currency'] !== $session->currency
            || (int) $snapshot['nominal_amount'] !== $session->nominal_amount
            || (int) $snapshot['total_payable_amount'] !== $session->total_payable_amount
            || array_sum(array_column($snapshot['fees'], 'calculated_amount')) + $session->nominal_amount !== $session->total_payable_amount) {
            throw new DomainException('Snapshot quote incohérent.');
        }
    }
}
