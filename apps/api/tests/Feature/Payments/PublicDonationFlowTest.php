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
use App\Enums\CheckoutStatus;
use App\Enums\PaymentStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicDonationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_ssr_starts_one_checkout_without_financial_side_effects(): void
    {
        $campaign = $this->campaign();

        $this->get(route('donations.amount', $campaign->slug))
            ->assertOk()
            ->assertSee('Votre don')
            ->assertSee('Les frais applicables seront calculés par le serveur avant confirmation.')
            ->assertDontSee('4 %')
            ->assertDontSee('1 %');

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

        $response = $this->post(route('donations.details', [$campaign->slug, $checkout->public_id]), [
            'donor_name' => 'Awa',
            'payer_mobile' => '771234567',
        ]);
        $response->assertSessionHasErrors('payer_mobile')
            ->assertSessionMissing('_old_input.payer_mobile')
            ->assertSessionHasInput('donor_name', 'Awa');
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
        $this->assertArrayNotHasKey('phone', $quoted->donor_snapshot);
        $this->assertSame('+221771234567', $quoted->payer_mobile_encrypted);
        $this->assertGreaterThanOrEqual($quoted->nominal_amount, $quoted->total_payable_amount);
        $this->assertSame(0, Donation::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(0, AppliedFee::query()->count());

        $this->get(route('donations.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('QUOTED')
            ->assertSee('Frais de plateforme')
            ->assertSee('400 FCFA')
            ->assertDontSee('Provision de transfert')
            ->assertDontSee('PLATFORM_FEE')
            ->assertDontSee('PLATFORM FEE')
            ->assertDontSee('PAYOUT PROVISION')
            ->assertDontSee('4 %')
            ->assertDontSee('1 %')
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

    public function test_proxied_checkout_generates_https_provider_return_urls_for_wave(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $urls = [];
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->withArgs(function ($payment, array $context) use (&$urls): bool {
            $urls = ['success_url' => $context['success_url'], 'error_url' => $context['error_url']];

            return true;
        })->andReturn(new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            'wave-proxied-session',
            'wave-proxied-reference',
            'https://pay.test/wave-proxied',
        ));
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->once()->andReturn($gateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);

        $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'REMOTE_ADDR' => '127.0.0.1',
        ])->post("http://test.barkeelu.com/collectes/{$campaign->slug}/don/paiement/{$checkout->public_id}/payer")
            ->assertRedirect('https://pay.test/wave-proxied');

        $return = "https://test.barkeelu.com/collectes/{$campaign->slug}/don/paiement/{$checkout->public_id}/retour";
        $this->assertSame($return, $urls['success_url']);
        $this->assertSame($return, $urls['error_url']);
    }

    public function test_direct_https_checkout_keeps_https_provider_return_urls_without_proxy_headers(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $this->providerAccount();
        $urls = [];
        $gateway = Mockery::mock(PaymentProviderGateway::class);
        $gateway->shouldReceive('initiate')->once()->withArgs(function ($payment, array $context) use (&$urls): bool {
            $urls = ['success_url' => $context['success_url'], 'error_url' => $context['error_url']];

            return true;
        })->andReturn(new ProviderInitiationResult(
            ProviderInitiationStatus::SENT_CONFIRMED,
            'wave-direct-session',
            'wave-direct-reference',
            'https://pay.test/wave-direct',
        ));
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->once()->andReturn($gateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);

        $this->withServerVariables([
            'HTTPS' => 'on',
            'REMOTE_ADDR' => '198.51.100.10',
        ])->post("https://test.barkeelu.com/collectes/{$campaign->slug}/don/paiement/{$checkout->public_id}/payer")
            ->assertRedirect('https://pay.test/wave-direct');

        $return = "https://test.barkeelu.com/collectes/{$campaign->slug}/don/paiement/{$checkout->public_id}/retour";
        $this->assertSame($return, $urls['success_url']);
        $this->assertSame($return, $urls['error_url']);
    }

    public function test_provider_return_is_public_read_only_and_pending_stays_pending(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $payment = $this->paymentForCheckout($checkout, PaymentStatus::PENDING);
        $checkout->update(['status' => CheckoutStatus::PAYMENT_PENDING, 'last_payment_id' => $payment->id]);
        $before = [
            'checkout' => DB::table('checkout_sessions')->where('id', $checkout->id)->first(),
            'payment' => DB::table('payments')->where('id', $payment->id)->first(),
            'donation' => DB::table('donations')->where('id', $checkout->donation_id)->first(),
            'ledger_transactions' => DB::table('ledger_transactions')->count(),
            'ledger_entries' => DB::table('ledger_entries')->count(),
            'applied_fees' => AppliedFee::query()->count(),
        ];

        $response = $this->call('GET', route('donations.provider-return.checkout', [$campaign->slug, $checkout->public_id]), [], [], [], ['HTTP_COOKIE' => '']);

        $response->assertOk()
            ->assertSee('Paiement en cours de confirmation')
            ->assertSee('Retour à la collecte')
            ->assertDontSee('Awa Ndiaye')
            ->assertDontSee('+221770000000')
            ->assertDontSee($payment->internal_reference)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->assertEquals($before['checkout'], DB::table('checkout_sessions')->where('id', $checkout->id)->first());
        $this->assertEquals($before['payment'], DB::table('payments')->where('id', $payment->id)->first());
        $this->assertEquals($before['donation'], DB::table('donations')->where('id', $checkout->donation_id)->first());
        $this->assertSame($before['ledger_transactions'], DB::table('ledger_transactions')->count());
        $this->assertSame($before['ledger_entries'], DB::table('ledger_entries')->count());
        $this->assertSame($before['applied_fees'], AppliedFee::query()->count());
        $this->assertSame(PaymentStatus::PENDING, $payment->refresh()->status);
        $this->assertSame(CheckoutStatus::PAYMENT_PENDING, $checkout->refresh()->status);
    }

    public function test_provider_return_rejects_unknown_checkout_or_campaign_mismatch(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $otherCampaign = $this->campaign();

        $this->call('GET', route('donations.provider-return.checkout', [$otherCampaign->slug, $checkout->public_id]), [], [], [], ['HTTP_COOKIE' => ''])->assertNotFound();
        $this->call('GET', route('donations.provider-return.checkout', [$campaign->slug, (string) Str::uuid()]), [], [], [], ['HTTP_COOKIE' => ''])->assertNotFound();
        $campaign->update(['visibility' => CampaignVisibility::PRIVATE]);
        $this->call('GET', route('donations.provider-return.checkout', [$campaign->slug, $checkout->public_id]), [], [], [], ['HTTP_COOKIE' => ''])->assertNotFound();
    }

    public function test_provider_return_renders_only_public_state_messages(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();

        $checkout->update(['status' => CheckoutStatus::PAID]);
        $this->call('GET', route('donations.provider-return.checkout', [$campaign->slug, $checkout->public_id]), [], [], [], ['HTTP_COOKIE' => ''])
            ->assertOk()
            ->assertSee('Paiement confirmé');

        $checkout->update(['status' => CheckoutStatus::UNKNOWN]);
        $this->call('GET', route('donations.provider-return.checkout', [$campaign->slug, $checkout->public_id]), [], [], [], ['HTTP_COOKIE' => ''])
            ->assertOk()
            ->assertSee('Confirmation du paiement en cours')
            ->assertDontSee('Paiement non confirmé');

        $checkout->update(['status' => CheckoutStatus::FAILED]);
        $this->call('GET', route('donations.provider-return.checkout', [$campaign->slug, $checkout->public_id]), [], [], [], ['HTTP_COOKIE' => ''])
            ->assertOk()
            ->assertSee('Paiement non confirmé');
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
        $this->assertNull($checkout->refresh()->payer_mobile_encrypted);
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
        $campaign->update(['fundraising_status' => CampaignFundraisingStatus::CLOSED]);
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
        $campaign->update(['fundraising_status' => CampaignFundraisingStatus::PAUSED]);
        $this->post(route('donations.verify.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertRedirect(route('donations.thanks.checkout', [$campaign->slug, $checkout->public_id]));
        $this->assertSame('PAID', $checkout->refresh()->status->value);
        $this->assertSame(1, Donation::query()->count());
    }

    public function test_paid_thanks_displays_confirmed_snapshot_without_private_data(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $payment = Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $this->providerAccount()->id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay_private_reference',
            'currency' => 'XOF',
            'amount' => $checkout->total_payable_amount,
            'status' => PaymentStatus::PAID,
            'idempotency_key' => 'paid-terminal-'.$checkout->public_id,
            'content_hash' => hash('sha256', 'paid-terminal'),
            'paid_at' => now(),
        ]);
        $checkout->update(['status' => CheckoutStatus::PAID, 'last_payment_id' => $payment->id]);

        $this->get(route('donations.thanks.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('Paiement confirmé')
            ->assertSee('10 000 FCFA')
            ->assertSee('10 400 FCFA')
            ->assertSee('Frais de plateforme')
            ->assertDontSee('PLATFORM_FEE')
            ->assertDontSee('PAYOUT_PROVISION')
            ->assertSee($payment->public_id)
            ->assertDontSee('Awa Ndiaye')
            ->assertDontSee('+221770000000')
            ->assertDontSee('pay_private_reference')
            ->assertDontSee('4 %')
            ->assertDontSee('1 %');
    }

    #[DataProvider('terminalPaymentStates')]
    public function test_terminal_payment_states_render_factual_failure_page(PaymentStatus $paymentStatus, string $message): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $payment = Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $this->providerAccount()->id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay_terminal_reference',
            'currency' => 'XOF',
            'amount' => $checkout->total_payable_amount,
            'status' => $paymentStatus,
            'idempotency_key' => 'terminal-'.$paymentStatus->value.'-'.$checkout->public_id,
            'content_hash' => hash('sha256', $paymentStatus->value),
        ]);
        $checkout->update(['status' => CheckoutStatus::FAILED, 'last_payment_id' => $payment->id]);

        $response = $this->get(route('donations.failed.checkout', [$campaign->slug, $checkout->public_id]));

        $response->assertOk()
            ->assertSee($message)
            ->assertDontSee('Awa Ndiaye')
            ->assertDontSee('+221770000000')
            ->assertDontSee('pay_terminal_reference');
        $paymentStatus === PaymentStatus::FAILED
            ? $response->assertSee('Réessayer le paiement')
            : $response->assertDontSee('Réessayer le paiement');
        if ($paymentStatus !== PaymentStatus::FAILED) {
            $this->post(route('donations.retry.checkout', [$campaign->slug, $checkout->public_id]))->assertStatus(409);
        }
    }

    public static function terminalPaymentStates(): array
    {
        return [
            'failed' => [PaymentStatus::FAILED, 'Le serveur a confirmé l’échec de cette tentative.'],
            'expired' => [PaymentStatus::EXPIRED, 'Cette session de paiement a expiré.'],
            'cancelled' => [PaymentStatus::CANCELLED, 'Cette session de paiement a été annulée.'],
        ];
    }

    public static function pausedOrClosedCampaigns(): array
    {
        return [
            'paused' => [CampaignFundraisingStatus::PAUSED],
            'closed' => [CampaignFundraisingStatus::CLOSED],
        ];
    }

    public function test_expired_checkout_without_payment_renders_expired_terminal_page(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();
        $checkout->update(['status' => CheckoutStatus::EXPIRED]);

        $this->get(route('donations.failed.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('Cette session de paiement a expiré.')
            ->assertDontSee('Réessayer le paiement');
    }

    public function test_cancelled_checkout_without_payment_renders_cancelled_terminal_page(): void
    {
        $campaign = $this->campaign();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10000]);
        $checkout = CheckoutSession::query()->firstOrFail();
        $checkout->update(['status' => CheckoutStatus::CANCELLED]);

        $this->get(route('donations.failed.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('Cette session de paiement a été annulée.')
            ->assertDontSee('Réessayer le paiement');
    }

    #[DataProvider('pausedOrClosedCampaigns')]
    public function test_existing_checkout_terminal_pages_remain_accessible_after_campaign_closure(CampaignFundraisingStatus $fundraisingStatus): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $pending = Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $this->providerAccount()->id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay-pending-'.$checkout->public_id,
            'currency' => 'XOF',
            'amount' => $checkout->total_payable_amount,
            'status' => PaymentStatus::PENDING,
            'idempotency_key' => 'pending-'.$checkout->public_id,
            'content_hash' => hash('sha256', 'pending'),
        ]);
        $checkout->update(['status' => CheckoutStatus::PAYMENT_PENDING, 'last_payment_id' => $pending->id]);
        $campaign->update(['fundraising_status' => $fundraisingStatus]);

        $this->get(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]))->assertOk();
        $this->get(route('donations.status.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertJson(['checkout_status' => 'PAYMENT_PENDING', 'payment_status' => 'PENDING']);

        $pending->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        $checkout->update(['status' => CheckoutStatus::PAID]);
        $this->get(route('donations.thanks.checkout', [$campaign->slug, $checkout->public_id]))->assertOk();

        $failed = Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $pending->provider_account_id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay-failed-'.$checkout->public_id,
            'currency' => 'XOF',
            'amount' => $checkout->total_payable_amount,
            'status' => PaymentStatus::FAILED,
            'idempotency_key' => 'failed-'.$checkout->public_id,
            'content_hash' => hash('sha256', 'failed'),
        ]);
        $checkout->update(['status' => CheckoutStatus::FAILED, 'last_payment_id' => $failed->id]);
        $this->get(route('donations.failed.checkout', [$campaign->slug, $checkout->public_id]))->assertOk();

        $this->get(route('donations.amount', $campaign->slug))->assertNotFound();
        $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10_000])->assertNotFound();
    }

    public function test_checkout_terminal_rejects_a_slug_from_another_campaign(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $otherCampaign = $this->campaign();
        $payment = Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $this->providerAccount()->id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay-slug-'.$checkout->public_id,
            'currency' => 'XOF',
            'amount' => $checkout->total_payable_amount,
            'status' => PaymentStatus::PENDING,
            'idempotency_key' => 'slug-'.$checkout->public_id,
            'content_hash' => hash('sha256', 'slug'),
        ]);
        $checkout->update(['status' => CheckoutStatus::PAYMENT_PENDING, 'last_payment_id' => $payment->id]);

        $this->get(route('donations.waiting.checkout', [$otherCampaign->slug, $checkout->public_id]))->assertNotFound();
    }

    public function test_private_and_targeted_campaigns_cannot_start_a_checkout(): void
    {
        foreach ([CampaignVisibility::PRIVATE, CampaignVisibility::TARGETED] as $visibility) {
            $campaign = $this->campaign();
            $campaign->update(['visibility' => $visibility]);

            $this->get(route('donations.amount', $campaign->slug))->assertNotFound();
            $this->post(route('donations.amount', $campaign->slug), ['nominal_amount' => 10_000])->assertNotFound();
        }
    }

    public function test_waiting_pages_keep_unknown_and_pending_actions_safe_without_javascript(): void
    {
        [$campaign, $checkout] = $this->confirmedCheckout();
        $payment = Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $this->providerAccount()->id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay_waiting_reference',
            'currency' => 'XOF',
            'amount' => $checkout->total_payable_amount,
            'status' => PaymentStatus::UNKNOWN,
            'idempotency_key' => 'waiting-unknown-'.$checkout->public_id,
            'content_hash' => hash('sha256', 'waiting-unknown'),
        ]);
        $checkout->update(['status' => CheckoutStatus::UNKNOWN, 'last_payment_id' => $payment->id]);

        $this->get(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('Vérifier le statut')
            ->assertSee(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]), false)
            ->assertDontSee('Réessayer le paiement')
            ->assertDontSee('+221770000000')
            ->assertDontSee('pay_waiting_reference');

        $payment->update(['status' => PaymentStatus::PENDING]);
        $checkout->update(['status' => CheckoutStatus::PAYMENT_PENDING]);

        $this->get(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]))
            ->assertOk()
            ->assertSee('Actualiser le statut')
            ->assertSee(route('donations.waiting.checkout', [$campaign->slug, $checkout->public_id]), false)
            ->assertDontSee('Vérifier le statut');
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

    private function paymentForCheckout(CheckoutSession $checkout, PaymentStatus $status): Payment
    {
        return Payment::query()->create([
            'public_id' => (string) Str::uuid(),
            'donation_id' => $checkout->donation_id,
            'provider_account_id' => $this->providerAccount()->id,
            'provider' => 'WAVE',
            'internal_reference' => 'pay-provider-return-'.$checkout->public_id,
            'currency' => $checkout->currency,
            'amount' => $checkout->total_payable_amount,
            'status' => $status,
            'idempotency_key' => 'provider-return-'.$checkout->public_id,
            'content_hash' => hash('sha256', 'provider-return-'.$checkout->public_id),
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
