<?php

namespace App\Services\Donations;

use App\Enums\DonationStatus;
use App\Models\AppliedFee;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\FeePolicy;
use App\Models\User;
use App\Services\Finance\FeeCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonationService
{
    public function __construct(private FeeCalculator $feeCalculator) {}

    public function create(Campaign $campaign, ?User $user, array $input): Donation
    {
        $payload = [
            'campaign_id' => $campaign->id,
            'donor_user_id' => $user?->id,
            'nominal_amount' => $input['nominal_amount'],
            'currency' => $input['currency'],
            'is_anonymous' => $input['is_anonymous'] ?? false,
            'donor_name' => $input['donor_name'] ?? null,
            'donor_email' => $input['donor_email'] ?? null,
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($campaign, $user, $input, $hash): Donation {
            $existing = Donation::query()->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();

            if ($existing !== null) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw new DomainException('Conflit d’idempotence donation.');
                }

                return $existing;
            }

            $platformPolicy = FeePolicy::query()
                ->where('code', 'PLATFORM_FEE')
                ->where('currency', $input['currency'])
                ->where('active', true)
                ->where('effective_from', '<=', now())
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', now()))
                ->first();

            $platformFee = $platformPolicy === null
                ? 0
                : $this->feeCalculator->calculate($input['nominal_amount'], $platformPolicy->rate_bps ?? 0, $platformPolicy->fixed_amount ?? 0);

            $donation = Donation::query()->create([
                'public_id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'donor_user_id' => $user?->id,
                'donor_name' => $input['donor_name'] ?? null,
                'donor_email' => $input['donor_email'] ?? null,
                'is_anonymous' => $input['is_anonymous'] ?? false,
                'currency' => $input['currency'],
                'nominal_amount' => $input['nominal_amount'],
                'platform_fee_amount' => $platformFee,
                'payout_provision_amount' => 0,
                'total_payable_amount' => $input['nominal_amount'] + $platformFee,
                'status' => DonationStatus::PENDING,
                'idempotency_key' => $input['idempotency_key'],
                'content_hash' => $hash,
                'created_by_user_id' => $user?->id,
            ]);

            if ($platformPolicy !== null) {
                AppliedFee::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'fee_policy_id' => $platformPolicy->id,
                    'source_type' => 'donation',
                    'source_reference' => $donation->public_id,
                    'fee_type' => $platformPolicy->fee_type,
                    'rate_bps' => $platformPolicy->rate_bps,
                    'fixed_amount' => $platformPolicy->fixed_amount,
                    'basis_amount' => $donation->nominal_amount,
                    'amount' => $platformFee,
                    'currency' => $donation->currency,
                    'payer' => 'donor',
                    'beneficiary' => 'platform',
                ]);
            }

            return $donation;
        });
    }
}
