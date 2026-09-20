<?php

namespace Tests\Feature\Checkout;

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
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Donations\DonationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CheckoutConfirmationDonationFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_delegates_exact_confirmed_snapshot_to_donation_factory(): void
    {
        $campaign = $this->campaign();
        $session = app(CheckoutQuoteService::class)->quote(
            app(CheckoutSessionService::class)->create($campaign, null, [
                'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'checkout-factory',
            ]),
            ['name' => 'Awa', 'email' => 'awa@example.test', 'phone' => '+221770000000', 'is_anonymous' => false, 'show_name' => true, 'show_amount' => false],
        );
        $donation = Donation::query()->create([
            'public_id' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'currency' => 'XOF',
            'nominal_amount' => 10_000, 'platform_fee_amount' => 0, 'payout_provision_amount' => 0,
            'total_payable_amount' => 10_000, 'status' => DonationStatus::PENDING,
            'idempotency_key' => 'factory-delegation', 'content_hash' => hash('sha256', 'factory-delegation'),
        ]);
        $factory = Mockery::mock(DonationFactory::class);
        $factory->shouldReceive('create')->once()->withArgs(function (ConfirmedDonationData $data) use ($session): bool {
            return $data->campaignId === $session->campaign_id
                && $data->donorSnapshot === $session->donor_snapshot
                && $data->nominalAmount === $session->nominal_amount
                && $data->platformFeeAmount === 0
                && $data->payoutProvisionAmount === 0
                && $data->totalPayableAmount === $session->total_payable_amount
                && $data->currency === $session->currency
                && $data->idempotencyKey === 'checkout-confirmation:'.$session->public_id
                && $data->sourceContext === 'checkout_confirmation';
        })->andReturn($donation);
        app()->instance(DonationFactory::class, $factory);

        $confirmed = app(CheckoutConfirmationService::class)->confirm($session, ['idempotency_key' => 'confirm-factory']);

        $this->assertSame($donation->id, $confirmed->donation_id);
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
            'title' => 'Campagne confirmation factory', 'slug' => 'confirmation-factory-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC,
        ]);
    }
}
