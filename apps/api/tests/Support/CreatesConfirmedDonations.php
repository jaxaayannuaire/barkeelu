<?php

namespace Tests\Support;

use App\Data\Donations\ConfirmedDonationData;
use App\Enums\DonationStatus;
use App\Models\AppliedFee;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\User;
use App\Services\Donations\DonationFactory;

trait CreatesConfirmedDonations
{
    protected function createPendingConfirmedDonation(
        Campaign $campaign,
        ?User $donor,
        string $idempotencyKey,
        array $overrides = [],
    ): Donation {
        $values = array_replace([
            'campaignId' => $campaign->id,
            'donorUserId' => $donor?->id,
            'donorSnapshot' => [
                'name' => $donor?->name ?? 'Donateur test',
                'email' => $donor?->email,
                'is_anonymous' => false,
            ],
            'nominalAmount' => 100,
            'platformFeeAmount' => 4,
            'payoutProvisionAmount' => 0,
            'totalPayableAmount' => 104,
            'currency' => 'XOF',
            'idempotencyKey' => $idempotencyKey,
            'contentHash' => hash('sha256', implode('|', [$campaign->id, $donor?->id ?? '', $idempotencyKey])),
            'createdByUserId' => $donor?->id,
            'sourceContext' => 'test_fixture',
        ], $overrides);

        $donation = app(DonationFactory::class)->create(new ConfirmedDonationData(...$values));

        $this->assertSame(DonationStatus::PENDING, $donation->status);
        $this->assertSame(0, AppliedFee::query()
            ->where('source_type', 'donation')
            ->where('source_reference', $donation->public_id)
            ->count());

        return $donation;
    }

    protected function assertPendingPaymentHasNoFinancialEffects(Payment $payment): void
    {
        $this->assertDatabaseMissing('ledger_transactions', [
            'business_key' => 'payment:'.$payment->public_id.':captured',
        ]);
        $this->assertDatabaseMissing('ledger_transactions', [
            'business_key' => 'payment:'.$payment->public_id.':unapplied',
        ]);
    }
}
