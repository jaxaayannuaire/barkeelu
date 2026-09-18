<?php

namespace Tests\Feature\Payments;

use App\Contracts\Payments\PaymentProviderGateway;
use App\Data\Payments\ProviderInitiationResult;
use App\Data\Payments\ProviderStatusResult;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\ProviderInitiationStatus;
use App\Models\AppliedFee;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\ProviderGatewayResolver;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
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
            ->assertDontSee('+221771234567')
            ->assertDontSee('Payer avec Wave');
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

    public function test_new_checkout_cannot_reach_legacy_payment_route(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000])->assertRedirect();

        $this->post('/collectes/'.$campaign->slug.'/don/paiement')->assertStatus(410);
        $this->assertSame(1, CheckoutSession::query()->count());
        $this->assertSame(0, Donation::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, AppliedFee::query()->count());
    }

    public function test_coordinates_create_server_quote_without_financial_side_effects(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();

        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa Ndiaye',
            'donor_email' => 'awa@example.test',
            'payer_mobile' => '+221771234567',
            'show_name' => '1',
            'show_amount' => '1',
        ])->assertRedirect(route('donations.checkout', [$campaign->slug, $checkout->public_id]));

        $quoted = $checkout->refresh();
        $this->assertSame('QUOTED', $quoted->status->value);
        $this->assertIsArray($quoted->fee_snapshot);
        $this->assertIsArray($quoted->donor_snapshot);
        $this->assertSame('Awa Ndiaye', $quoted->donor_snapshot['name']);
        $this->assertSame('awa@example.test', $quoted->donor_snapshot['email']);
        $this->assertSame('+221771234567', $quoted->donor_snapshot['phone']);
        $this->assertGreaterThanOrEqual($quoted->nominal_amount, $quoted->total_payable_amount);
        $this->assertSame(0, Donation::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, AppliedFee::query()->count());

        $this->get(route('donations.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('QUOTED')
            ->assertSee('Total à payer')
            ->assertSee((string) number_format($quoted->total_payable_amount, 0, ',', ' '))
            ->assertDontSee('awa@example.test')
            ->assertDontSee('+221771234567')
            ->assertDontSee('fee_snapshot');
    }

    public function test_confirmation_creates_one_donation_without_payment_or_applied_fee(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();
        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa Ndiaye', 'payer_mobile' => '+221771234567',
        ]);

        $confirm = $this->post(route('donations.confirm', [$campaign->slug, $checkout->public_id]));
        $confirm->assertRedirect(route('donations.checkout', [$campaign->slug, $checkout->public_id]));
        $confirmed = $checkout->refresh();
        $this->assertSame('CONFIRMED', $confirmed->status->value);
        $this->assertNotNull($confirmed->donation_id);
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, AppliedFee::query()->count());
        $donation = $confirmed->donation;
        $this->assertSame($confirmed->nominal_amount, $donation->nominal_amount);
        $this->assertSame($confirmed->total_payable_amount, $donation->total_payable_amount);

        $this->post(route('donations.confirm', [$campaign->slug, $checkout->public_id]))
            ->assertRedirect(route('donations.checkout', [$campaign->slug, $checkout->public_id]));
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_expired_quote_is_rejected_until_explicit_requote(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();
        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa', 'payer_mobile' => '+221771234567',
        ]);
        $checkout->update(['quote_expires_at' => now()->subMinute()]);

        $this->post(route('donations.confirm', [$campaign->slug, $checkout->public_id]))->assertStatus(409);
        $this->assertSame(0, Donation::query()->count());

        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa', 'payer_mobile' => '+221771234567',
        ])->assertRedirect();
        $this->assertSame('QUOTED', $checkout->refresh()->status->value);
        $this->post(route('donations.confirm', [$campaign->slug, $checkout->public_id]))->assertRedirect();
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_confirmed_checkout_payment_redirects_through_provider_gateway(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->andReturn(new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            'wave-ssr-session',
            'wave-ssr-reference',
            'https://pay.test/wave-ssr',
        ));
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->once()->andReturn($gateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);

        $response = $this->post(route('donations.pay', [$campaign->slug, $checkout->public_id]));

        $response->assertRedirect('https://pay.test/wave-ssr');
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame('PAYMENT_PENDING', $checkout->refresh()->status->value);
        $this->get(route('donations.status.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertJson(['checkout_status' => 'PAYMENT_PENDING', 'payment_status' => 'PENDING']);
    }

    public function test_unknown_payment_cannot_be_retried_and_status_is_private_minimal(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->andReturn(new ProviderInitiationResult(ProviderInitiationStatus::SENT_UNKNOWN));
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->once()->andReturn($gateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);

        $this->post(route('donations.pay', [$campaign->slug, $checkout->public_id]))->assertRedirect(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]));
        $this->get(route('donations.status.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertJson(['checkout_status' => 'UNKNOWN', 'payment_status' => 'UNKNOWN'])
            ->assertJsonMissing(['payer_mobile' => '+221770000000']);
        $this->post(route('donations.retry.checkout', [$campaign->slug, $checkout->public_id]))->assertStatus(409);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_failed_payment_retry_creates_new_payment_for_same_donation(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $failedGateway = Mockery::mock(PaymentProviderGateway::class);
        $failedGateway->shouldReceive('initiate')->once()->andReturn(new ProviderInitiationResult(ProviderInitiationStatus::NOT_SENT));
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->once()->andReturn($failedGateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);
        $this->post(route('donations.pay', [$campaign->slug, $checkout->public_id]))->assertRedirect(route('donations.failed.checkout', [$campaign->slug, $checkout->public_id]));

        $retryGateway = Mockery::mock(PaymentProviderGateway::class);
        $retryGateway->shouldReceive('initiate')->once()->andReturn(new ProviderInitiationResult(ProviderInitiationStatus::SENT_CONFIRMED, 'wave-retry', 'retry-ref', 'https://pay.test/retry'));
        $retryResolver = Mockery::mock(ProviderGatewayResolver::class);
        $retryResolver->shouldReceive('for')->once()->andReturn($retryGateway);
        $this->app->instance(ProviderGatewayResolver::class, $retryResolver);
        $this->post(route('donations.retry.checkout', [$campaign->slug, $checkout->public_id]))->assertRedirect('https://pay.test/retry');

        $this->assertSame(2, Payment::query()->count());
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame('PAYMENT_PENDING', $checkout->refresh()->status->value);
    }

    public function test_unknown_verification_can_redirect_to_thanks_after_server_side_paid_resolution(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->andReturn(new ProviderInitiationResult(ProviderInitiationStatus::SENT_UNKNOWN, 'wave-unknown-ssr', 'unknown-ref'));
        $gateway->shouldReceive('retrieve')->once()->andReturn(new ProviderStatusResult('PAID', 'wave-unknown-ssr', 'provider-paid', 'unknown-ref'));
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->twice()->andReturn($gateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);

        $this->post(route('donations.pay', [$campaign->slug, $checkout->public_id]))->assertRedirect(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]));
        $this->post(route('donations.verify.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertRedirect(route('donations.thanks.checkout', [$campaign->slug, $checkout->public_id]));
        $this->assertSame('PAID', $checkout->refresh()->status->value);
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_multiple_active_wave_accounts_are_not_selected_arbitrarily(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $this->providerAccount();

        $this->post(route('donations.pay', [$campaign->slug, $checkout->public_id]))->assertStatus(409);
        $this->assertSame(0, Payment::query()->count());
    }

    private function confirmedCheckout(): array
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();
        $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa Ndiaye', 'payer_mobile' => '+221770000000',
        ]);
        $this->post(route('donations.confirm', [$campaign->slug, $checkout->public_id]));

        return [$campaign, $checkout->refresh()];
    }

    private function providerAccount(): ProviderAccount
    {
        return ProviderAccount::query()->create([
            'public_id' => (string) Str::uuid(), 'provider' => 'WAVE', 'name' => 'Wave SSR',
            'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true,
        ]);
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
