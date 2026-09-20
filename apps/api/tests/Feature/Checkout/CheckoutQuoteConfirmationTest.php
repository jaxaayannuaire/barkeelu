<?php

namespace Tests\Feature\Checkout;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\CheckoutStatus;
use App\Enums\FeeType;
use App\Models\AppliedFee;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\FeePolicy;
use App\Models\User;
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutQuoteConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_snapshots_active_platform_and_payout_policies_without_applied_fees(): void
    {
        [$campaign] = $this->campaignContext();
        $platform = $this->policy('PLATFORM_FEE', FeeType::PLATFORM_FEE, 250, true);
        $provision = $this->policy('PAYOUT_PROVISION_FIXTURE', FeeType::PAYOUT_PROVISION, 75, true);
        $session = $this->createCheckout($campaign);

        $quoted = app(CheckoutQuoteService::class)->quote($session, $this->donorInput());

        $this->assertSame(CheckoutStatus::QUOTED, $quoted->status);
        $this->assertSame(10_000 + 250 + 75, $quoted->total_payable_amount);
        $this->assertCount(2, $quoted->fee_snapshot['fees']);
        $this->assertSame($platform->id, $quoted->fee_snapshot['fees'][0]['policy_id']);
        $this->assertSame($provision->id, $quoted->fee_snapshot['fees'][1]['policy_id']);
        $this->assertSame('HALF_UP_INTEGER', $quoted->fee_snapshot['fees'][0]['rounding_rule']);
        $this->assertSame('Awa', $quoted->donor_snapshot['name']);
        $this->assertSame(0, AppliedFee::query()->count());
    }

    public function test_quote_keeps_payer_mobile_encrypted_outside_donor_snapshot_and_quote_history(): void
    {
        [$campaign] = $this->campaignContext();
        $this->policy('PLATFORM_FEE_PII', FeeType::PLATFORM_FEE, 250, true);
        $session = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput());

        $this->assertArrayNotHasKey('phone', $session->donor_snapshot);
        $this->assertSame('+221770000000', $session->payer_mobile_encrypted);
        $this->assertNotSame('+221770000000', DB::table('checkout_sessions')->where('id', $session->id)->value('payer_mobile_encrypted'));
        $this->assertStringNotContainsString('+221770000000', $session->toJson());

        $session->update(['quote_expires_at' => now()->subMinute()]);
        $renewed = app(CheckoutQuoteService::class)->quote($session->refresh(), $this->donorInput());
        $this->assertArrayNotHasKey('donor_snapshot', $renewed->fee_snapshot['quote_history'][0]);
    }

    public function test_inactive_payout_policy_is_excluded_without_any_hardcoded_rate(): void
    {
        [$campaign] = $this->campaignContext();
        $this->policy('PLATFORM_FEE_INACTIVE_CASE', FeeType::PLATFORM_FEE, 250, true);
        $this->policy('PAYOUT_PROVISION_INACTIVE', FeeType::PAYOUT_PROVISION, 100, false);
        $session = $this->createCheckout($campaign);

        $quoted = app(CheckoutQuoteService::class)->quote($session, $this->donorInput());

        $this->assertSame(10_250, $quoted->total_payable_amount);
        $this->assertSame([FeeType::PLATFORM_FEE->value], array_column($quoted->fee_snapshot['fees'], 'fee_type'));
    }

    public function test_policy_changes_after_quote_do_not_change_existing_snapshot_and_new_quote_is_explicit(): void
    {
        [$campaign] = $this->campaignContext();
        $policy = $this->policy('PLATFORM_FEE_VERSIONED', FeeType::PLATFORM_FEE, 200, true);
        $session = $this->createCheckout($campaign);
        $quoted = app(CheckoutQuoteService::class)->quote($session, $this->donorInput());
        $originalSnapshot = $quoted->fee_snapshot;

        $policy->update(['rate_bps' => 900, 'updated_at' => now()->addMinute()]);
        $this->assertSame($originalSnapshot, $quoted->refresh()->fee_snapshot);

        $newQuote = app(CheckoutQuoteService::class)->quote($quoted, $this->donorInput());
        $this->assertNotSame($originalSnapshot['fees'][0]['policy_version'], $newQuote->fee_snapshot['fees'][0]['policy_version']);
        $this->assertSame(10_900, $newQuote->total_payable_amount);
    }

    public function test_expired_quote_requires_a_new_explicit_quote_and_cannot_be_confirmed(): void
    {
        [$campaign] = $this->campaignContext();
        $this->policy('PLATFORM_FEE_EXPIRY', FeeType::PLATFORM_FEE, 200, true);
        $session = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput());
        $session->update(['quote_expires_at' => now()->subMinute()]);

        $renewed = app(CheckoutQuoteService::class)->quote($session->refresh(), $this->donorInput(['show_amount' => true]));
        $this->assertCount(1, $renewed->fee_snapshot['quote_history']);
        $renewed->update(['quote_expires_at' => now()->subMinute()]);

        $this->expectException(DomainException::class);
        app(CheckoutConfirmationService::class)->confirm($renewed, ['idempotency_key' => 'confirm-expired']);
    }

    public function test_three_explicit_quotes_keep_a_flat_deterministic_history(): void
    {
        [$campaign] = $this->campaignContext();
        $this->policy('PLATFORM_FEE_HISTORY', FeeType::PLATFORM_FEE, 200, true);
        $session = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput(['name' => 'D1']));

        $session->update(['quote_expires_at' => now()->subMinute()]);
        $session = app(CheckoutQuoteService::class)->quote($session->refresh(), $this->donorInput(['name' => 'D2']));
        $session->update(['quote_expires_at' => now()->subMinute()]);
        $session = app(CheckoutQuoteService::class)->quote($session->refresh(), $this->donorInput(['name' => 'D3']));

        $history = $session->fee_snapshot['quote_history'];
        $this->assertCount(2, $history);
        foreach ($history as $entry) {
            $this->assertArrayNotHasKey('quote_history', $entry);
            $this->assertArrayNotHasKey('quote_history', $entry['fee_snapshot']);
            $this->assertArrayNotHasKey('confirmation', $entry['fee_snapshot']);
            $this->assertArrayNotHasKey('donor_snapshot', $entry);
        }
    }

    public function test_confirmation_creates_one_snapshot_based_donation_and_no_applied_fee(): void
    {
        [$campaign] = $this->campaignContext();
        $this->policy('PLATFORM_FEE_CONFIRM', FeeType::PLATFORM_FEE, 300, true);
        $this->policy('PAYOUT_PROVISION_CONFIRM', FeeType::PAYOUT_PROVISION, 50, true);
        $session = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput());

        $confirmed = app(CheckoutConfirmationService::class)->confirm($session, ['idempotency_key' => 'confirm-1']);
        $retry = app(CheckoutConfirmationService::class)->confirm($confirmed, ['idempotency_key' => 'confirm-1']);
        $donation = Donation::query()->firstOrFail();

        $this->assertSame(CheckoutStatus::CONFIRMED, $confirmed->status);
        $this->assertSame($confirmed->donation_id, $donation->id);
        $this->assertSame($confirmed->total_payable_amount, $donation->total_payable_amount);
        $this->assertSame(300, $donation->platform_fee_amount);
        $this->assertSame(50, $donation->payout_provision_amount);
        $this->assertSame('Awa', $donation->donor_name);
        $this->assertSame('awa@example.test', $donation->donor_email);
        $this->assertFalse($donation->is_anonymous);
        $this->assertNull($confirmed->donor_snapshot);
        $this->assertStringNotContainsString('awa@example.test', $confirmed->toJson());
        $this->assertStringNotContainsString('show_name', $confirmed->toJson());
        $this->assertSame($confirmed->donation_id, $retry->donation_id);
        $this->assertSame(1, Donation::query()->count());
        $this->assertSame(0, AppliedFee::query()->count());
    }

    public function test_confirmation_conflicting_idempotency_key_is_rejected(): void
    {
        [$campaign] = $this->campaignContext();
        $session = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput());
        $service = app(CheckoutConfirmationService::class);
        $confirmed = $service->confirm($session, ['idempotency_key' => 'confirm-a']);

        $this->expectException(DomainException::class);
        $service->confirm($confirmed, ['idempotency_key' => 'confirm-b']);
    }

    public function test_same_confirmation_key_with_tampered_confirmation_hash_is_rejected(): void
    {
        [$campaign] = $this->campaignContext();
        $session = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput());
        $service = app(CheckoutConfirmationService::class);
        $confirmed = $service->confirm($session, ['idempotency_key' => 'confirm-content']);
        $snapshot = $confirmed->fee_snapshot;
        $snapshot['confirmation']['content_hash'] = hash('sha256', 'tampered');
        $confirmed->update(['fee_snapshot' => $snapshot]);

        $this->expectException(DomainException::class);
        $service->confirm($confirmed->refresh(), ['idempotency_key' => 'confirm-content']);
    }

    public function test_quote_and_confirm_api_keep_donor_snapshot_private(): void
    {
        [$campaign] = $this->campaignContext();
        $session = $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'api-quote',
        ])->assertCreated()->json('data.public_id');

        $this->postJson('/api/v1/checkout-sessions/'.$session.'/quote', $this->donorInput())
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::QUOTED->value)
            ->assertJsonMissingPath('data.donor_snapshot')
            ->assertJsonMissingPath('data.fee_snapshot');

        $this->postJson('/api/v1/checkout-sessions/'.$session.'/confirm', ['idempotency_key' => 'api-confirm'])
            ->assertOk()
            ->assertJsonPath('data.status', CheckoutStatus::CONFIRMED->value)
            ->assertJsonMissingPath('data.donor_snapshot');
    }

    public function test_guest_and_authenticated_confirmation_preserve_donor_identity_mode(): void
    {
        [$campaign, $user] = $this->campaignContext();
        $guest = app(CheckoutQuoteService::class)->quote($this->createCheckout($campaign), $this->donorInput());
        $authSession = $this->createCheckout($campaign, $user);
        $auth = app(CheckoutQuoteService::class)->quote($authSession, $this->donorInput(['is_anonymous' => true]));

        $guestDonation = app(CheckoutConfirmationService::class)->confirm($guest, ['idempotency_key' => 'guest-confirm'])->donation;
        $authDonation = app(CheckoutConfirmationService::class)->confirm($auth, ['idempotency_key' => 'auth-confirm'])->donation;

        $this->assertNull($guestDonation->donor_user_id);
        $this->assertSame($user->id, $authDonation->donor_user_id);
        $this->assertTrue($authDonation->is_anonymous);
    }

    private function createCheckout(Campaign $campaign, ?User $user = null): CheckoutSession
    {
        return app(CheckoutSessionService::class)->create($campaign, $user, [
            'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'checkout-'.Str::uuid(),
        ]);
    }

    private function donorInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Awa', 'email' => 'awa@example.test', 'phone' => '+221770000000',
            'is_anonymous' => false, 'show_name' => true, 'show_amount' => false,
        ], $overrides);
    }

    private function policy(string $code, FeeType $type, int $rate, bool $active): FeePolicy
    {
        return FeePolicy::query()->create([
            'public_id' => (string) Str::uuid(), 'code' => $code, 'fee_type' => $type,
            'rate_bps' => $rate, 'currency' => 'XOF', 'effective_from' => Carbon::now()->subMinute(),
            'active' => $active,
        ]);
    }

    private function campaignContext(): array
    {
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Bénéficiaire', 'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
        $campaign = Campaign::query()->create([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Campagne quote', 'slug' => 'quote-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED->value, 'fundraising_status' => CampaignFundraisingStatus::OPEN->value,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE->value, 'visibility' => CampaignVisibility::PUBLIC->value,
        ]);

        return [$campaign, $user];
    }
}
