<?php

namespace App\Services\Checkout;

use App\Data\Donations\ConfirmedDonationData;
use App\Enums\CheckoutStatus;
use App\Enums\FeeType;
use App\Models\CheckoutSession;
use App\Services\Donations\DonationFactory;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutConfirmationService
{
    public function __construct(private readonly DonationFactory $donations) {}

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
            $fees = collect($locked->fee_snapshot['fees']);
            $donation = $this->donations->create(new ConfirmedDonationData(
                campaignId: $locked->campaign_id,
                donorUserId: $locked->donor_user_id,
                donorSnapshot: $locked->donor_snapshot,
                nominalAmount: $locked->nominal_amount,
                platformFeeAmount: (int) $fees->where('fee_type', FeeType::PLATFORM_FEE->value)->sum('calculated_amount'),
                payoutProvisionAmount: (int) $fees->where('fee_type', FeeType::PAYOUT_PROVISION->value)->sum('calculated_amount'),
                totalPayableAmount: $locked->total_payable_amount,
                currency: $locked->currency,
                idempotencyKey: $donationKey,
                contentHash: $confirmationHash,
                createdByUserId: $locked->donor_user_id,
                sourceContext: 'checkout_confirmation',
            ));

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
                'donor_snapshot' => null,
            ]);

            return $locked->refresh();
        });
    }

    private function assertConfirmationIdempotence(CheckoutSession $session, array $input): void
    {
        $confirmation = $session->fee_snapshot['confirmation'] ?? null;
        $donation = $session->donation()->lockForUpdate()->first();
        if ($confirmation === null
            || $confirmation['idempotency_key'] !== $input['idempotency_key']
            || ! is_string($confirmation['content_hash'] ?? null)
            || $donation === null
            || ! hash_equals($confirmation['content_hash'], $donation->content_hash)) {
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
