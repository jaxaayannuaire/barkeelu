<?php

namespace Tests\Feature\Checkout;

use App\Contracts\Payments\PaymentProviderGateway;
use App\Data\Payments\ProviderInitiationResult;
use App\Data\Payments\ProviderStatusResult;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\CheckoutStatus;
use App\Enums\DonationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProviderInitiationStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\AppliedFee;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ProviderGatewayResolver;
use App\Services\Payments\WaveCheckoutService;
use App\Services\Payments\WaveWebhookMapper;
use App\Services\Webhooks\WebhookIngressService;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_checkout_creates_payment_and_reuses_same_donation(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $gateway = $this->gateway(new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            'wave-session-1',
            'client-ref-1',
            'https://pay.test/session-1',
            now()->addMinutes(15)->toIso8601String(),
            'OPEN',
        ));
        $this->useGateway($gateway);

        $result = app(CheckoutPaymentService::class)->initiate($session, $account, $this->paymentInput('attempt-1'));

        $this->assertSame(PaymentStatus::PENDING, $result->payment->status);
        $this->assertSame(CheckoutStatus::PAYMENT_PENDING, $session->refresh()->status);
        $this->assertSame($session->donation_id, $result->payment->donation_id);
        $this->assertSame('https://pay.test/session-1', $result->redirectUrl);
        $this->assertSame(1, $session->donation()->count());
        $this->assertSame('+221770000000', $result->payment->payer_mobile_encrypted);
        $this->assertNull($session->refresh()->payer_mobile_encrypted);
    }

    public function test_same_initiation_key_is_idempotent_and_different_content_conflicts(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $gateway = $this->gateway(new ProviderInitiationResult(ProviderInitiationStatus::SENT_CONFIRMED, 'wave-session-idempotent', 'ref', 'https://pay.test/ref'));
        $this->useGateway($gateway);
        $service = app(CheckoutPaymentService::class);
        $input = $this->paymentInput('attempt-idempotent');
        $first = $service->initiate($session, $account, $input);
        $second = $service->initiate($session->refresh(), $account, $input);

        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertDatabaseCount('payments', 1);

        $this->expectException(DomainException::class);
        $service->initiate($session->refresh(), $account, array_merge($input, ['payer_mobile' => '+221780000000']));
    }

    public function test_not_sent_is_failed_without_making_checkout_unknown(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $this->useGateway($this->gateway(new ProviderInitiationResult(ProviderInitiationStatus::NOT_SENT)));

        $result = app(CheckoutPaymentService::class)->initiate($session, $account, $this->paymentInput('attempt-not-sent'));

        $this->assertSame(PaymentStatus::FAILED, $result->payment->status);
        $this->assertSame(CheckoutStatus::CONFIRMED, $session->refresh()->status);
        $this->assertSame('+221770000000', $result->payment->payer_mobile_encrypted);
        $this->assertSame('+221770000000', $session->payer_mobile_encrypted);
    }

    public function test_sent_unknown_persists_provider_evidence_without_changing_unknown_states(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $expiresAt = now()->addMinutes(12)->toIso8601String();
        $gateway = $this->gateway(new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_UNKNOWN,
            'wave-unknown-session',
            'wave-unknown-client-reference',
            null,
            $expiresAt,
        ));
        $this->useGateway($gateway);

        $result = app(CheckoutPaymentService::class)->initiate($session, $account, $this->paymentInput('attempt-unknown-evidence'));
        $payment = $result->payment->refresh();

        $this->assertSame(PaymentStatus::UNKNOWN, $payment->status);
        $this->assertSame(CheckoutStatus::UNKNOWN, $session->refresh()->status);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertDatabaseMissing('ledger_transactions', [
            'business_key' => 'payment:'.$result->payment->public_id.':captured',
        ]);
        $this->assertSame('wave-unknown-session', $payment->provider_checkout_session_id);
        $this->assertSame('wave-unknown-client-reference', $payment->provider_client_reference);
        $this->assertNotNull($payment->provider_checkout_expires_at);
        $this->assertSame($expiresAt, $payment->provider_checkout_expires_at->toIso8601String());
        $this->assertSame('+221770000000', $payment->payer_mobile_encrypted);
        $this->assertNull($session->refresh()->payer_mobile_encrypted);
    }

    public function test_failed_retry_uses_mobile_encrypted_on_previous_payment(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->twice()->andReturn(
            new ProviderInitiationResult(ProviderInitiationStatus::SENT_CONFIRMED, 'wave-first', 'first-ref'),
            new ProviderInitiationResult(ProviderInitiationStatus::SENT_CONFIRMED, 'wave-retry', 'retry-ref'),
        );
        $this->useGateway($gateway);
        $service = app(CheckoutPaymentService::class);

        $first = $service->initiate($session, $account, $this->paymentInput('attempt-first'));
        app(PaymentService::class)->applyProviderState($first->payment, [
            'provider_account_id' => $account->id,
            'internal_reference' => $first->payment->internal_reference,
            'amount' => $first->payment->amount,
            'currency' => $first->payment->currency,
            'provider_status' => 'FAILED',
        ]);
        $service->syncFromPayment($first->payment->refresh());

        $retry = $service->initiate($session->refresh(), $account, [
            'idempotency_key' => 'attempt-retry',
            'success_url' => 'https://pay.test/retry',
            'error_url' => 'https://pay.test/retry',
        ]);

        $this->assertSame(PaymentStatus::PENDING, $retry->payment->status);
        $this->assertSame('+221770000000', $retry->payment->payer_mobile_encrypted);
        $this->assertNull($session->refresh()->payer_mobile_encrypted);
    }

    public function test_correlated_wave_payment_failed_marks_payment_and_checkout_failed_without_financial_effect(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $this->useGateway($this->gateway(new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            'wave-failed-session',
            'wave-failed-reference',
        )));
        $payment = app(CheckoutPaymentService::class)->initiate($session, $account, $this->paymentInput('attempt-wave-failed'))->payment;

        $mapped = app(WaveWebhookMapper::class)->map([
            'id' => 'evt-wave-failed',
            'type' => 'checkout.session.payment_failed',
            'data' => [
                'id' => 'wave-failed-session',
                'checkout_status' => 'failed',
                'payment_status' => 'failed',
            ],
        ], $account->id);
        $failed = app(PaymentService::class)->applyProviderState($mapped['payment'], $mapped['event']);
        app(CheckoutPaymentService::class)->syncFromPayment($failed);

        $this->assertSame(PaymentStatus::FAILED, $payment->refresh()->status);
        $this->assertSame(CheckoutStatus::FAILED, $session->refresh()->status);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertDatabaseMissing('ledger_transactions', [
            'business_key' => 'payment:'.$payment->public_id.':captured',
        ]);
    }

    public function test_valid_wave_test_event_leaves_existing_checkout_unchanged(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        config(['services.wave.webhook_signing_secret' => 'webhook-secret']);
        $raw = json_encode(['id' => 'evt-wave-test-checkout', 'type' => 'test.test_event', 'data' => []], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.$raw, 'webhook-secret');
        $valid = app(WaveCheckoutService::class)->signatureIsValid($raw, "t={$timestamp},v1={$signature}");
        $event = app(WebhookIngressService::class)->receive($account, $raw, [], $valid);

        (new ProcessWebhookEvent($event->id))->handle(app(PaymentService::class), app(CheckoutPaymentService::class));

        $this->assertSame(WebhookEventStatus::PROCESSED, $event->refresh()->status);
        $this->assertSame(CheckoutStatus::CONFIRMED, $session->refresh()->status);
        $this->assertSame(DonationStatus::PENDING, $session->donation->refresh()->status);
        $this->assertSame(0, $session->donation->payments()->count());
        $this->assertSame(0, AppliedFee::query()->count());
    }

    public function test_legacy_snapshot_mobile_is_used_once_then_purged_after_provider_emission(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $legacySnapshot = $session->donor_snapshot;
        $legacySnapshot['phone'] = '+221781234567';
        $session->update(['payer_mobile_encrypted' => null, 'donor_snapshot' => $legacySnapshot]);
        $this->useGateway($this->gateway(new ProviderInitiationResult(ProviderInitiationStatus::SENT_CONFIRMED, 'wave-legacy', 'legacy-ref')));

        $payment = app(CheckoutPaymentService::class)->initiate($session->refresh(), $account, [
            'idempotency_key' => 'attempt-legacy',
            'success_url' => 'https://pay.test/legacy',
            'error_url' => 'https://pay.test/legacy',
        ])->payment;

        $this->assertSame('+221781234567', $payment->payer_mobile_encrypted);
        $this->assertNull($session->refresh()->payer_mobile_encrypted);
        $this->assertArrayNotHasKey('phone', $session->donor_snapshot);
    }

    public function test_sent_unknown_blocks_retry_and_retrieve_can_resolve_to_paid(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $gateway = $this->gateway(new ProviderInitiationResult(ProviderInitiationStatus::SENT_UNKNOWN));
        $gateway->shouldReceive('retrieve')->once()->andReturn(new ProviderStatusResult('PAID', 'wave-unknown', 'provider-paid', 'ref-paid'));
        $this->useGateway($gateway);
        $service = app(CheckoutPaymentService::class);
        $result = $service->initiate($session, $account, $this->paymentInput('attempt-unknown'));

        $this->assertSame(PaymentStatus::UNKNOWN, $result->payment->status);
        $this->assertSame(CheckoutStatus::UNKNOWN, $session->refresh()->status);
        try {
            $service->initiate($session->refresh(), $account, $this->paymentInput('attempt-retry'));
            $this->fail('Un retry UNKNOWN doit être refusé.');
        } catch (DomainException) {
            $this->addToAssertionCount(1);
        }

        $result->payment->update(['provider_client_reference' => $result->payment->internal_reference]);
        $resolved = $service->resolveUnknown($result->payment->refresh());
        $this->assertSame(PaymentStatus::PAID, $resolved->status);
        $this->assertSame(CheckoutStatus::PAID, $session->refresh()->status);
        $this->assertSame('wave-unknown', $resolved->provider_checkout_session_id);
        $this->assertSame(1, AppliedFee::query()->count());
        $this->assertDatabaseCount('ledger_transactions', 1);

        $this->expectException(DomainException::class);
        $service->resolveUnknown($resolved);
    }

    public function test_unknown_retrieve_failed_persists_session_id_without_financial_effect(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $gateway = $this->gateway(new ProviderInitiationResult(ProviderInitiationStatus::SENT_UNKNOWN));
        $gateway->shouldReceive('retrieve')->once()->andReturn(new ProviderStatusResult('FAILED', 'wave-failed', null, 'ref-failed'));
        $this->useGateway($gateway);
        $service = app(CheckoutPaymentService::class);
        $result = $service->initiate($session, $account, $this->paymentInput('attempt-unknown-failed'));
        $result->payment->update(['provider_client_reference' => $result->payment->internal_reference]);

        $resolved = $service->resolveUnknown($result->payment->refresh());

        $this->assertSame(PaymentStatus::FAILED, $resolved->status);
        $this->assertSame(CheckoutStatus::FAILED, $session->refresh()->status);
        $this->assertSame('wave-failed', $resolved->provider_checkout_session_id);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertDatabaseMissing('ledger_transactions', [
            'business_key' => 'payment:'.$resolved->public_id.':captured',
        ]);
    }

    public function test_paid_server_side_materializes_checkout_applied_fees_only_at_success(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $this->useGateway($this->gateway(new ProviderInitiationResult(ProviderInitiationStatus::SENT_CONFIRMED, 'wave-paid', 'ref-paid')));
        $result = app(CheckoutPaymentService::class)->initiate($session, $account, $this->paymentInput('attempt-paid'));
        $this->assertSame(0, AppliedFee::query()->count());

        app(PaymentService::class)->applyProviderState($result->payment, [
            'provider_account_id' => $result->payment->provider_account_id,
            'internal_reference' => $result->payment->internal_reference,
            'amount' => $result->payment->amount,
            'currency' => $result->payment->currency,
            'provider_status' => 'PAID',
            'provider_payment_id' => 'paid-1',
        ]);
        app(CheckoutPaymentService::class)->syncFromPayment($result->payment->refresh());

        $this->assertSame(1, AppliedFee::query()->count());
        $this->assertSame(CheckoutStatus::PAID, $session->refresh()->status);
    }

    private function gateway(ProviderInitiationResult $initiation): PaymentProviderGateway
    {
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->andReturn($initiation);

        return $gateway;
    }

    private function useGateway(PaymentProviderGateway $gateway): void
    {
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->andReturn($gateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);
    }

    private function confirmedCheckout(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Beneficiary', 'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
        $campaign = Campaign::query()->create([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Checkout payment', 'slug' => 'checkout-payment-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC,
        ]);
        $account = ProviderAccount::query()->create([
            'public_id' => (string) Str::uuid(), 'provider' => 'WAVE', 'name' => 'Wave test',
            'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true,
        ]);
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'checkout-payment-'.Str::uuid(),
        ]);
        $session = app(CheckoutQuoteService::class)->quote($session, [
            'name' => 'Awa', 'email' => 'awa@example.test', 'phone' => '+221770000000',
            'is_anonymous' => false, 'show_name' => true, 'show_amount' => false,
        ]);
        $session = app(CheckoutConfirmationService::class)->confirm($session, ['idempotency_key' => 'confirm-payment-'.Str::uuid()]);

        return [$session, $account];
    }

    private function paymentInput(string $key): array
    {
        return [
            'idempotency_key' => $key,
            'payer_mobile' => '+221770000000',
            'success_url' => 'https://barkeelu.test/success',
            'error_url' => 'https://barkeelu.test/error',
        ];
    }
}
