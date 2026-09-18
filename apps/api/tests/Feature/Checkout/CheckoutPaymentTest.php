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
use App\Enums\PaymentStatus;
use App\Enums\ProviderInitiationStatus;
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
        $this->assertSame('wave-unknown-session', $payment->provider_checkout_session_id);
        $this->assertSame('wave-unknown-client-reference', $payment->provider_client_reference);
        $this->assertNotNull($payment->provider_checkout_expires_at);
        $this->assertSame($expiresAt, $payment->provider_checkout_expires_at->toIso8601String());
        $this->assertSame('+221771234567', $payment->payer_mobile_encrypted);
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

        $resolved = $service->resolveUnknown($result->payment->refresh());
        $this->assertSame(PaymentStatus::PAID, $resolved->status);
        $this->assertSame(CheckoutStatus::PAID, $session->refresh()->status);
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

        $this->assertSame(2, AppliedFee::query()->count());
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
            'payer_mobile' => '+221771234567',
            'success_url' => 'https://barkeelu.test/success',
            'error_url' => 'https://barkeelu.test/error',
        ];
    }
}
