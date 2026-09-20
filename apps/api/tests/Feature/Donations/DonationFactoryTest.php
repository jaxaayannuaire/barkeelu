<?php

namespace Tests\Feature\Donations;

use App\Data\Donations\ConfirmedDonationData;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\DonationStatus;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use App\Services\Donations\DonationFactory;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_donation_from_confirmed_snapshot_values(): void
    {
        $donation = app(DonationFactory::class)->create($this->data());

        $this->assertSame(DonationStatus::PENDING, $donation->status);
        $this->assertSame(10_000, $donation->nominal_amount);
        $this->assertSame(400, $donation->platform_fee_amount);
        $this->assertSame(100, $donation->payout_provision_amount);
        $this->assertSame(10_500, $donation->total_payable_amount);
        $this->assertSame('Awa', $donation->donor_name);
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_same_idempotency_key_and_content_returns_existing_donation(): void
    {
        $factory = app(DonationFactory::class);
        $data = $this->data();
        $first = $factory->create($data);
        $second = $factory->create($data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_same_idempotency_key_with_different_content_is_rejected(): void
    {
        $factory = app(DonationFactory::class);
        $data = $this->data();
        $factory->create($data);

        $this->expectException(DomainException::class);
        $factory->create(new ConfirmedDonationData(
            campaignId: $data->campaignId,
            donorUserId: $data->donorUserId,
            donorSnapshot: $data->donorSnapshot,
            nominalAmount: $data->nominalAmount,
            platformFeeAmount: $data->platformFeeAmount,
            payoutProvisionAmount: $data->payoutProvisionAmount,
            totalPayableAmount: $data->totalPayableAmount,
            currency: $data->currency,
            idempotencyKey: $data->idempotencyKey,
            contentHash: hash('sha256', 'different'),
            createdByUserId: $data->createdByUserId,
            sourceContext: $data->sourceContext,
        ));
    }

    public function test_unbalanced_confirmed_amounts_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        app(DonationFactory::class)->create($this->data(['totalPayableAmount' => 10_499]));
    }

    private function data(array $overrides = []): ConfirmedDonationData
    {
        $campaign = $this->campaign();
        $values = array_merge([
            'campaignId' => $campaign->id,
            'donorUserId' => null,
            'donorSnapshot' => ['name' => 'Awa', 'email' => 'awa@example.test', 'is_anonymous' => false],
            'nominalAmount' => 10_000,
            'platformFeeAmount' => 400,
            'payoutProvisionAmount' => 100,
            'totalPayableAmount' => 10_500,
            'currency' => 'XOF',
            'idempotencyKey' => 'confirmed-donation:'.Str::uuid(),
            'contentHash' => hash('sha256', 'confirmed-snapshot'),
            'createdByUserId' => null,
            'sourceContext' => 'checkout_confirmation',
        ], $overrides);

        return new ConfirmedDonationData(...$values);
    }

    private function campaign(): Campaign
    {
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Bénéficiaire', 'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);

        return Campaign::query()->create([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Campagne factory', 'slug' => 'factory-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC,
        ]);
    }
}
