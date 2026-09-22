<?php

namespace Tests\Feature\Payments;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\CheckoutStatus;
use App\Enums\DonationStatus;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\AppliedFee;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\FeePolicy;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Webhooks\WebhookIngressService;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class WebhookEventTest extends TestCase
{
    use CreatesConfirmedDonations;
    use RefreshDatabase;

    public function test_valid_raw_webhook_is_persisted_deduplicated_and_processed_once(): void
    {
        [$payment, $account] = $this->payment();
        $payload = $this->payload($payment, 'PAID');
        Queue::fake();
        $ingress = app(WebhookIngressService::class);
        $event = $ingress->receive($account, json_encode($payload, JSON_THROW_ON_ERROR), ['Authorization' => 'secret', 'X-Request-Id' => 'request'], true);
        $duplicate = $ingress->receive($account, json_encode($payload, JSON_THROW_ON_ERROR), ['Authorization' => 'secret'], true);
        $this->assertSame($event->id, $duplicate->id);
        $this->assertSame('[REDACTED]', $event->headers_redacted['authorization']);
        $this->assertDatabaseCount('webhook_events', 1);
        Queue::assertPushed(ProcessWebhookEvent::class, 2);
        app()->call([new ProcessWebhookEvent($event->id), 'handle']);
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
        $this->assertSame(WebhookEventStatus::PROCESSED, $event->refresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_invalid_signature_has_no_business_effect_and_late_pending_does_not_downgrade_paid(): void
    {
        [$payment, $account] = $this->payment();
        $ingress = app(WebhookIngressService::class);
        $invalid = $ingress->receive($account, json_encode($this->payload($payment, 'PAID'), JSON_THROW_ON_ERROR), ['Authorization' => 'secret'], false);
        $this->assertSame(WebhookEventStatus::IGNORED, $invalid->status);
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
        $paid = $ingress->receive($account, json_encode($this->payload($payment, 'PAID', 'event-paid'), JSON_THROW_ON_ERROR), [], true);
        app()->call([new ProcessWebhookEvent($paid->id), 'handle']);
        $late = $ingress->receive($account, json_encode($this->payload($payment, 'PENDING', 'event-pending'), JSON_THROW_ON_ERROR), [], true);
        app()->call([new ProcessWebhookEvent($late->id), 'handle']);
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
    }

    public function test_process_webhook_event_requires_checkout_and_gateway_dependencies(): void
    {
        $parameters = (new \ReflectionMethod(ProcessWebhookEvent::class, 'handle'))->getParameters();

        $this->assertFalse($parameters[1]->allowsNull());
        $this->assertFalse($parameters[1]->isDefaultValueAvailable());
        $this->assertFalse($parameters[2]->allowsNull());
        $this->assertFalse($parameters[2]->isDefaultValueAvailable());
    }

    public function test_paid_webhook_processed_by_container_syncs_associated_checkout(): void
    {
        [$payment, $account, $campaign, $donation] = $this->payment([
            'totalPayableAmount' => 105,
            'payoutProvisionAmount' => 1,
        ]);
        $platformFee = FeePolicy::query()->where('code', 'PLATFORM_FEE')->firstOrFail();
        $payoutFee = FeePolicy::query()->where('code', 'PAYOUT_PROVISION_WORKING')->firstOrFail();
        $checkout = CheckoutSession::query()->create([
            'public_id' => (string) Str::uuid(),
            'campaign_id' => $campaign->id,
            'donation_id' => $donation->id,
            'status' => CheckoutStatus::PAYMENT_PENDING,
            'currency' => 'XOF',
            'nominal_amount' => 100,
            'total_payable_amount' => 105,
            'fee_snapshot' => ['fees' => [
                ['policy_id' => $platformFee->id, 'fee_type' => 'PLATFORM_FEE', 'basis_points' => 400, 'fixed_amount' => null, 'calculation_base_amount' => 100, 'calculated_amount' => 4, 'currency' => 'XOF'],
                ['policy_id' => $payoutFee->id, 'fee_type' => 'PAYOUT_PROVISION', 'basis_points' => 100, 'fixed_amount' => null, 'calculation_base_amount' => 100, 'calculated_amount' => 1, 'currency' => 'XOF'],
            ]],
            'checkout_expires_at' => now()->addHour(),
            'confirmed_at' => now(),
            'idempotency_key' => 'checkout-'.Str::uuid(),
            'content_hash' => hash('sha256', 'checkout-'.$payment->id),
            'last_payment_id' => $payment->id,
        ]);
        $event = app(WebhookIngressService::class)->receive($account, json_encode($this->payload($payment, 'PAID'), JSON_THROW_ON_ERROR), [], true);

        app()->call([new ProcessWebhookEvent($event->id), 'handle']);

        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
        $this->assertSame(DonationStatus::PAID, $donation->refresh()->status);
        $this->assertSame(CheckoutStatus::PAID, $checkout->refresh()->status);
        $this->assertSame(WebhookEventStatus::PROCESSED, $event->refresh()->status);
        $this->assertDatabaseHas('ledger_transactions', ['business_key' => 'payment:'.$payment->public_id.':captured', 'status' => 'POSTED']);
        $this->assertSame(2, AppliedFee::query()->where('source_reference', $donation->public_id)->count());
    }

    private function payment(array $donationOverrides = []): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'Beneficiary', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'Campaign', 'slug' => 'campaign-'.Str::lower(Str::random(8)), 'description' => 'Description', 'goal_amount' => 100000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST_PROVIDER', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = $this->createPendingConfirmedDonation($campaign, $user, 'donation-'.Str::uuid(), $donationOverrides);
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => $donation->total_payable_amount, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);

        return [$payment, $account, $campaign, $donation];
    }

    private function payload($payment, string $status, ?string $eventId = null): array
    {
        return ['event_id' => $eventId ?? 'event-'.Str::uuid(), 'event_type' => 'payment.updated', 'internal_reference' => $payment->internal_reference, 'amount' => $payment->amount, 'currency' => $payment->currency, 'provider_status' => $status, 'provider_payment_id' => 'provider-'.$payment->id];
    }
}
