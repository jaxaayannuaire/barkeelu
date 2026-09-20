<?php

namespace Tests\Feature\Donations;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyDonationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_donation_endpoint_is_gone_without_financial_effect(): void
    {
        [$campaign, $user] = $this->campaignContext();

        $response = $this->actingAs($user)->postJson('/api/v1/campaigns/'.$campaign->public_id.'/donations', [
            'nominal_amount' => 10_000,
            'currency' => 'XOF',
            'idempotency_key' => 'legacy-direct-donation',
        ]);

        $response->assertGone()
            ->assertJsonPath('code', 'DIRECT_DONATION_ENDPOINT_DEPRECATED')
            ->assertJsonPath('replacement.method', 'POST')
            ->assertJsonPath('replacement.path', '/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions');

        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('applied_fees', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    private function campaignContext(): array
    {
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(),
            'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Bénéficiaire',
            'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
        $campaign = Campaign::query()->create([
            'public_id' => (string) Str::uuid(),
            'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'beneficiary_id' => $beneficiary->id,
            'title' => 'Campagne legacy',
            'slug' => 'legacy-'.Str::lower(Str::random(8)),
            'description' => 'Description',
            'goal_amount' => 100_000,
            'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED->value,
            'fundraising_status' => CampaignFundraisingStatus::OPEN->value,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE->value,
            'visibility' => CampaignVisibility::PUBLIC->value,
        ]);

        return [$campaign, $user];
    }
}
