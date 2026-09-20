<?php

namespace Tests\Feature\Payments;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\PaymentStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\Beneficiary;
use App\Models\Campaign;
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
        (new ProcessWebhookEvent($event->id))->handle(app(PaymentService::class));
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
        (new ProcessWebhookEvent($paid->id))->handle(app(PaymentService::class));
        $late = $ingress->receive($account, json_encode($this->payload($payment, 'PENDING', 'event-pending'), JSON_THROW_ON_ERROR), [], true);
        (new ProcessWebhookEvent($late->id))->handle(app(PaymentService::class));
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
    }

    private function payment(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'Beneficiary', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'Campaign', 'slug' => 'campaign-'.Str::lower(Str::random(8)), 'description' => 'Description', 'goal_amount' => 100000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST_PROVIDER', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = $this->createPendingConfirmedDonation($campaign, $user, 'donation-'.Str::uuid());
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);

        return [$payment, $account];
    }

    private function payload($payment, string $status, ?string $eventId = null): array
    {
        return ['event_id' => $eventId ?? 'event-'.Str::uuid(), 'event_type' => 'payment.updated', 'internal_reference' => $payment->internal_reference, 'amount' => $payment->amount, 'currency' => $payment->currency, 'provider_status' => $status, 'provider_payment_id' => 'provider-'.$payment->id];
    }
}
