<?php

namespace Tests\Feature\Payments;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\AppliedFee;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicDonationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_ssr_starts_one_checkout_without_financial_side_effects(): void
    {
        $campaign = $this->campaign();

        $this->get(route('donations.amount', $campaign->slug))->assertOk()->assertSee('Votre don');

        $first = $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();
        $first->assertRedirect(route('donations.details', [$campaign->slug, $checkout->public_id]));
        $this->assertSame(1, CheckoutSession::query()->count());
        $this->assertSame(0, Donation::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, AppliedFee::query()->count());

        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000])
            ->assertRedirect(route('donations.details', [$campaign->slug, $checkout->public_id]));
        $this->assertSame(1, CheckoutSession::query()->count());

        $this->get(route('donations.amount', $campaign->slug))
            ->assertOk()
            ->assertDontSee('Frais plateforme');

        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 11000])->assertStatus(409);

        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa Ndiaye',
            'payer_mobile' => '+221771234567',
        ])->assertRedirect(route('donations.checkout', [$campaign->slug, $checkout->public_id]));

        $this->get(route('donations.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee($checkout->public_id)
            ->assertSee('10 000 FCFA')
            ->assertDontSee('Frais plateforme')
            ->assertDontSee('+221771234567');
    }

    public function test_ssr_details_validates_phone_without_persisting_private_snapshot(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 2000]);
        $checkout = CheckoutSession::query()->firstOrFail();

        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa',
            'payer_mobile' => '771234567',
        ])->assertSessionHasErrors('payer_mobile');
        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), ['donor_name' => 'Awa'])
            ->assertSessionHasErrors('payer_mobile');

        $this->assertNull($checkout->refresh()->donor_snapshot);
    }

    public function test_unpublished_or_closed_campaign_is_rejected(): void
    {
        $campaign = $this->campaign();
        $campaign->update(['status' => CampaignStatus::DRAFT]);

        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 1000])->assertStatus(404);
    }

    private function campaign(): Campaign
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id,
        ]);

        return Campaign::query()->create([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Collecte', 'slug' => 'collecte-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC,
        ]);
    }
}
