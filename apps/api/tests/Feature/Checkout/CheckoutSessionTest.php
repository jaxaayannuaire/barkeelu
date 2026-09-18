<?php

namespace Tests\Feature\Checkout;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\CheckoutStatus;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\Donation;
use App\Models\User;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Checkout\CheckoutStateService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_a_public_checkout_session_without_private_snapshot_leak(): void
    {
        [$campaign] = $this->campaignContext();

        $response = $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'XOF',
            'nominal_amount' => 10_000,
            'idempotency_key' => 'checkout-guest-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', CheckoutStatus::DRAFT->value)
            ->assertJsonPath('data.currency', 'XOF')
            ->assertJsonPath('data.nominal_amount', 10_000)
            ->assertJsonMissingPath('data.donor_snapshot')
            ->assertJsonMissingPath('data.idempotency_key')
            ->assertJsonMissingPath('data.content_hash');

        $session = CheckoutSession::query()->firstOrFail();
        $this->assertNull($session->donor_user_id);
        $this->assertTrue(Str::isUuid($session->public_id));
        $this->assertNotSame((string) $session->id, $session->public_id);
    }

    public function test_authenticated_checkout_session_records_the_authenticated_donor_only(): void
    {
        [$campaign, $user] = $this->campaignContext();

        $response = $this->actingAs($user)->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'XOF',
            'nominal_amount' => 2_500,
            'idempotency_key' => 'checkout-auth-1',
        ]);

        $response->assertCreated()->assertJsonMissingPath('data.donor_snapshot');
        $this->assertSame($user->id, CheckoutSession::query()->firstOrFail()->donor_user_id);
    }

    public function test_checkout_input_requires_positive_xof_amount(): void
    {
        [$campaign] = $this->campaignContext();

        $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'EUR',
            'nominal_amount' => 0,
            'idempotency_key' => 'checkout-invalid-1',
        ])->assertUnprocessable()->assertJsonValidationErrors(['currency', 'nominal_amount']);
    }

    public function test_checkout_rejects_unpublished_or_closed_campaigns(): void
    {
        [$unpublished] = $this->campaignContext(['status' => CampaignStatus::DRAFT->value]);
        [$closed] = $this->campaignContext(['fundraising_status' => CampaignFundraisingStatus::CLOSED->value]);

        foreach ([$unpublished, $closed] as $campaign) {
            $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
                'currency' => 'XOF',
                'nominal_amount' => 100,
                'idempotency_key' => 'checkout-rejected-'.$campaign->id,
            ])->assertNotFound();
        }
    }

    public function test_same_idempotency_key_and_content_returns_the_same_session(): void
    {
        [$campaign] = $this->campaignContext();
        $payload = ['currency' => 'XOF', 'nominal_amount' => 1_000, 'idempotency_key' => 'checkout-idempotent'];

        $first = $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', $payload)->assertCreated();

        $this->assertSame($first->json('data.public_id'), $second->json('data.public_id'));
        $this->assertDatabaseCount('checkout_sessions', 1);
    }

    public function test_same_idempotency_key_with_different_critical_content_is_a_conflict(): void
    {
        [$campaign] = $this->campaignContext();
        $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'XOF', 'nominal_amount' => 1_000, 'idempotency_key' => 'checkout-conflict',
        ])->assertCreated();

        $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'XOF', 'nominal_amount' => 1_001, 'idempotency_key' => 'checkout-conflict',
        ])->assertStatus(409);
    }

    public function test_get_checkout_session_returns_public_fields_only(): void
    {
        [$campaign] = $this->campaignContext();
        $created = $this->postJson('/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions', [
            'currency' => 'XOF', 'nominal_amount' => 1_000, 'idempotency_key' => 'checkout-get',
        ])->assertCreated();
        $session = CheckoutSession::query()->firstOrFail();
        $session->update(['donor_snapshot' => ['phone' => '+221770000000', 'email' => 'private@example.test']]);

        $this->getJson('/api/v1/checkout-sessions/'.$created->json('data.public_id'))
            ->assertOk()
            ->assertJsonPath('data.public_id', $session->public_id)
            ->assertJsonMissingPath('data.donor_snapshot')
            ->assertJsonMissingPath('data.idempotency_key')
            ->assertJsonMissingPath('data.content_hash');
    }

    public function test_state_machine_accepts_only_the_declared_transitions(): void
    {
        [$campaign] = $this->campaignContext();
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 1_000, 'idempotency_key' => 'state-machine',
        ]);
        $states = app(CheckoutStateService::class);

        $states->transition($session, CheckoutStatus::QUOTED);
        $states->transition($session->refresh(), CheckoutStatus::CONFIRMED);
        $states->transition($session->refresh(), CheckoutStatus::PAYMENT_PENDING);
        $this->expectException(DomainException::class);
        $states->transition($session->refresh(), CheckoutStatus::EXPIRED);
    }

    public function test_expiration_requires_an_expired_pre_provider_checkout(): void
    {
        [$campaign] = $this->campaignContext();
        $states = app(CheckoutStateService::class);

        foreach ([CheckoutStatus::DRAFT, CheckoutStatus::QUOTED, CheckoutStatus::CONFIRMED] as $status) {
            $session = app(CheckoutSessionService::class)->create($campaign, null, [
                'currency' => 'XOF', 'nominal_amount' => 100, 'idempotency_key' => 'expired-'.$status->value,
            ]);
            $session->update(['status' => $status]);
            Carbon::setTestNow($session->created_at->addHours(2));
            try {
                $this->assertSame(CheckoutStatus::EXPIRED, $states->transition($session->refresh(), CheckoutStatus::EXPIRED)->status);
            } finally {
                Carbon::setTestNow();
            }
        }
    }

    public function test_expiration_before_deadline_and_unknown_without_evidence_are_rejected(): void
    {
        [$campaign] = $this->campaignContext();
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 100, 'idempotency_key' => 'not-expired',
        ]);
        $states = app(CheckoutStateService::class);

        try {
            $states->transition($session, CheckoutStatus::EXPIRED);
            $this->fail('Une session non expirée ne doit pas devenir EXPIRED.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(DomainException::class);
        $states->transition($session, CheckoutStatus::UNKNOWN);
    }

    public function test_paid_requires_server_side_authority_and_payment_pending_cannot_expire(): void
    {
        [$campaign] = $this->campaignContext();
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 100, 'idempotency_key' => 'paid-authority',
        ]);
        $states = app(CheckoutStateService::class);
        $states->transition($session, CheckoutStatus::QUOTED);
        $states->transition($session->refresh(), CheckoutStatus::CONFIRMED);
        $states->transition($session->refresh(), CheckoutStatus::PAYMENT_PENDING);

        try {
            $states->transition($session->refresh(), CheckoutStatus::PAID);
            $this->fail('Un appel client ne doit pas atteindre PAID.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(DomainException::class);
        $states->transition($session->refresh(), CheckoutStatus::EXPIRED);
    }

    public function test_donation_id_is_unique_when_assigned_to_checkout_sessions(): void
    {
        [$campaign] = $this->campaignContext();
        $donation = Donation::query()->create([
            'public_id' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'currency' => 'XOF',
            'nominal_amount' => 100, 'platform_fee_amount' => 0, 'payout_provision_amount' => 0,
            'total_payable_amount' => 100, 'status' => 'PENDING', 'idempotency_key' => 'donation-checkout-unique',
            'content_hash' => hash('sha256', 'donation-checkout-unique'),
        ]);
        $service = app(CheckoutSessionService::class);
        $first = $service->create($campaign, null, ['currency' => 'XOF', 'nominal_amount' => 100, 'idempotency_key' => 'unique-a']);
        $second = $service->create($campaign, null, ['currency' => 'XOF', 'nominal_amount' => 100, 'idempotency_key' => 'unique-b']);
        $first->update(['donation_id' => $donation->id]);

        $this->expectException(QueryException::class);
        $second->update(['donation_id' => $donation->id]);
    }

    private function campaignContext(array $overrides = []): array
    {
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Bénéficiaire', 'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
        $campaign = Campaign::query()->create(array_merge([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Campagne checkout', 'slug' => 'checkout-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED->value,
            'fundraising_status' => CampaignFundraisingStatus::OPEN->value,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE->value,
            'visibility' => CampaignVisibility::PUBLIC->value,
        ], $overrides));

        return [$campaign, $user];
    }
}
