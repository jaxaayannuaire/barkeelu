<?php

namespace App\Services\Donations;

use App\Data\Donations\ConfirmedDonationData;
use App\Enums\DonationStatus;
use App\Models\Donation;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationFactory
{
    public function create(ConfirmedDonationData $data): Donation
    {
        $this->assertValid($data);

        try {
            return DB::transaction(fn (): Donation => $this->findOrCreate($data));
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            $donation = Donation::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertMatchingContent($donation, $data);

            return $donation;
        }
    }

    private function findOrCreate(ConfirmedDonationData $data): Donation
    {
        $donation = Donation::query()
            ->where('idempotency_key', $data->idempotencyKey)
            ->lockForUpdate()
            ->first();

        if ($donation !== null) {
            $this->assertMatchingContent($donation, $data);

            return $donation;
        }

        return Donation::query()->create([
            'public_id' => (string) Str::uuid(),
            'campaign_id' => $data->campaignId,
            'donor_user_id' => $data->donorUserId,
            'donor_name' => $data->donorSnapshot['name'],
            'donor_email' => $data->donorSnapshot['email'],
            'is_anonymous' => $data->donorSnapshot['is_anonymous'],
            'currency' => $data->currency,
            'nominal_amount' => $data->nominalAmount,
            'platform_fee_amount' => $data->platformFeeAmount,
            'payout_provision_amount' => $data->payoutProvisionAmount,
            'total_payable_amount' => $data->totalPayableAmount,
            'status' => DonationStatus::PENDING,
            'idempotency_key' => $data->idempotencyKey,
            'content_hash' => $data->contentHash,
            'created_by_user_id' => $data->createdByUserId,
        ]);
    }

    private function assertMatchingContent(Donation $donation, ConfirmedDonationData $data): void
    {
        if (! hash_equals($donation->content_hash, $data->contentHash)) {
            throw new DomainException('Conflit de création Donation.');
        }
    }

    private function assertValid(ConfirmedDonationData $data): void
    {
        $donor = $data->donorSnapshot;
        if ($data->campaignId < 1
            || $data->nominalAmount < 1
            || $data->platformFeeAmount < 0
            || $data->payoutProvisionAmount < 0
            || $data->totalPayableAmount !== $data->nominalAmount + $data->platformFeeAmount + $data->payoutProvisionAmount
            || $data->currency === ''
            || $data->idempotencyKey === ''
            || $data->contentHash === ''
            || $data->sourceContext === ''
            || ! is_string($donor['name'] ?? null)
            || $donor['name'] === ''
            || ! is_null($donor['email'] ?? null) && ! is_string($donor['email'])
            || ! is_bool($donor['is_anonymous'] ?? null)) {
            throw new DomainException('Données de Donation confirmée invalides.');
        }
    }
}
