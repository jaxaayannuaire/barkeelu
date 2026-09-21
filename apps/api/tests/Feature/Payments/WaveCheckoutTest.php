<?php

namespace Tests\Feature\Payments;

use App\Console\Commands\PurgeExpiredPayerMobiles;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\DonationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProviderInitiationStatus;
use App\Enums\WebhookEventStatus;
use App\Jobs\ProcessWebhookEvent;
use App\Models\AppliedFee;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ProviderGatewayResolver;
use App\Services\Payments\WaveCheckoutService;
use App\Services\Payments\WaveGateway;
use App\Services\Payments\WaveWebhookMapper;
use App\Services\Webhooks\WebhookIngressService;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class WaveCheckoutTest extends TestCase
{
    use CreatesConfirmedDonations;
    use RefreshDatabase;

    public function test_checkout_post_and_get_are_signed_and_restrict_the_selected_payer_mobile(): void
    {
        [$payment] = $this->payment();
        config(['services.wave.api_key' => 'wave-api-key', 'services.wave.request_signing_secret' => 'request-secret', 'services.wave.base_url' => 'https://api.wave.test']);
        Http::fake([
            'https://api.wave.test/v1/checkout/sessions' => Http::response(['id' => 'session-1', 'wave_launch_url' => 'https://pay.wave.test/session-1', 'when_expires' => now()->addMinutes(15)->toIso8601String()]),
            'https://api.wave.test/v1/checkout/sessions/session-1' => Http::response(['id' => 'session-1', 'checkout_status' => 'open']),
        ]);

        $wave = app(WaveCheckoutService::class);
        $created = $wave->initiate($payment, '+221771234567', 'https://barkeelu.test/attente', 'https://barkeelu.test/attente');
        $this->assertSame('session-1', $created['id']);
        $this->assertSame('open', $wave->retrieve('session-1')['checkout_status']);

        Http::assertSent(function (ClientRequest $request): bool {
            if ($request->method() !== 'POST') {
                return false;
            }
            $body = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
            $header = $request->header('Wave-Signature')[0] ?? '';
            preg_match('/t=(\d+),v1=([a-f0-9]{64})/', $header, $matches);

            return $request->url() === 'https://api.wave.test/v1/checkout/sessions'
                && $body['amount'] === '105'
                && $body['currency'] === 'XOF'
                && $body['restrict_payer_mobile'] === '+221771234567'
                && isset($matches[1], $matches[2])
                && hash_equals(hash_hmac('sha256', $matches[1].$request->body(), 'request-secret'), $matches[2]);
        });
        Http::assertSent(function (ClientRequest $request): bool {
            if ($request->method() !== 'GET') {
                return false;
            }
            $header = $request->header('Wave-Signature')[0] ?? '';
            preg_match('/t=(\d+),v1=([a-f0-9]{64})/', $header, $matches);

            return $request->url() === 'https://api.wave.test/v1/checkout/sessions/session-1'
                && isset($matches[1], $matches[2])
                && hash_equals(hash_hmac('sha256', $matches[1], 'request-secret'), $matches[2]);
        });
    }

    public function test_unknown_with_checkout_session_id_uses_direct_get(): void
    {
        [$payment] = $this->payment();
        $payment->update([
            'status' => PaymentStatus::UNKNOWN,
            'provider_checkout_session_id' => 'cos-direct',
            'provider_client_reference' => $payment->internal_reference,
        ]);
        $this->configureWave();
        Http::fake([
            'https://api.wave.test/v1/checkout/sessions/cos-direct' => Http::response($this->checkoutPayload($payment, 'cos-direct')),
        ]);

        $result = app(WaveGateway::class)->retrieve($payment->refresh());

        $this->assertSame('PAID', $result->status);
        $this->assertSame('cos-direct', $result->operationReference);
        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api.wave.test/v1/checkout/sessions/cos-direct');
    }

    public function test_unknown_without_checkout_session_id_searches_only_persisted_client_reference(): void
    {
        [$payment] = $this->payment();
        $payment->update([
            'status' => PaymentStatus::UNKNOWN,
            'provider_checkout_session_id' => null,
            'provider_client_reference' => $payment->internal_reference,
        ]);
        $this->configureWave();
        Http::fake([
            'https://api.wave.test/v1/checkout/sessions/search*' => Http::response([
                'result' => [$this->checkoutPayload($payment, 'cos-search')],
            ]),
        ]);

        $result = app(WaveGateway::class)->retrieve($payment->refresh());

        $this->assertSame('PAID', $result->status);
        $this->assertSame('cos-search', $result->operationReference);
        Http::assertSentCount(1);
        Http::assertSent(function (ClientRequest $request) use ($payment): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.wave.test/v1/checkout/sessions/search?client_reference='.urlencode($payment->internal_reference);
        });
    }

    public function test_unknown_without_persisted_client_reference_does_not_search(): void
    {
        [$payment] = $this->payment();
        $payment->update([
            'status' => PaymentStatus::UNKNOWN,
            'provider_checkout_session_id' => null,
            'provider_client_reference' => null,
        ]);
        $this->configureWave();
        Http::fake();

        $result = app(WaveGateway::class)->retrieve($payment->refresh());

        $this->assertSame('UNKNOWN', $result->status);
        Http::assertNothingSent();
    }

    public function test_unknown_search_keeps_unknown_when_result_is_absent_ambiguous_or_incoherent(): void
    {
        [$payment] = $this->payment();
        $payment->update([
            'status' => PaymentStatus::UNKNOWN,
            'provider_checkout_session_id' => null,
            'provider_client_reference' => $payment->internal_reference,
        ]);
        $this->configureWave();
        $exact = $this->checkoutPayload($payment, 'cos-search');
        $cases = [
            [],
            [array_merge($exact, ['client_reference' => 'other-reference'])],
            [array_merge($exact, ['amount' => '106'])],
            [array_merge($exact, ['currency' => 'EUR'])],
            [$exact, array_merge($exact, ['id' => 'cos-search-2'])],
        ];

        foreach ($cases as $result) {
            Http::fake(['https://api.wave.test/v1/checkout/sessions/search*' => Http::response(['result' => $result])]);

            $resolved = app(WaveGateway::class)->retrieve($payment->refresh());

            $this->assertSame('UNKNOWN', $resolved->status);
            $this->assertNull($resolved->operationReference);
            Http::assertSentCount(1);
        }
    }

    public function test_unknown_search_maps_failed_without_financial_transition(): void
    {
        [$payment] = $this->payment();
        $payment->update([
            'status' => PaymentStatus::UNKNOWN,
            'provider_checkout_session_id' => null,
            'provider_client_reference' => $payment->internal_reference,
        ]);
        $this->configureWave();
        Http::fake([
            'https://api.wave.test/v1/checkout/sessions/search*' => Http::response([
                'result' => [$this->checkoutPayload($payment, 'cos-failed', 'expired', 'cancelled')],
            ]),
        ]);

        $result = app(WaveGateway::class)->retrieve($payment->refresh());

        $this->assertSame('FAILED', $result->status);
        $this->assertSame('cos-failed', $result->operationReference);
    }

    public function test_post_errors_after_possible_emission_are_sent_unknown(): void
    {
        [$payment] = $this->payment();
        config([
            'services.wave.api_key' => 'wave-api-key',
            'services.wave.request_signing_secret' => 'request-secret',
            'services.wave.base_url' => 'https://api.wave.test',
        ]);

        foreach ([429, 500, 502, 503] as $status) {
            Http::fake(['https://api.wave.test/v1/checkout/sessions' => Http::response([], $status)]);

            $result = app(WaveGateway::class)->initiate($payment, [
                'payer_mobile' => '+221771234567',
                'success_url' => 'https://barkeelu.test/success',
                'error_url' => 'https://barkeelu.test/error',
            ]);

            $this->assertSame(ProviderInitiationStatus::SENT_UNKNOWN, $result->status, "HTTP {$status}");
            Http::assertSentCount(1);
        }
    }

    public function test_timeout_after_post_is_sent_unknown(): void
    {
        [$payment] = $this->payment();
        config([
            'services.wave.api_key' => 'wave-api-key',
            'services.wave.request_signing_secret' => 'request-secret',
            'services.wave.base_url' => 'https://api.wave.test',
        ]);
        Http::fake(['https://api.wave.test/v1/checkout/sessions' => Http::failedConnection('timeout')]);

        $result = app(WaveGateway::class)->initiate($payment, [
            'payer_mobile' => '+221771234567',
            'success_url' => 'https://barkeelu.test/success',
            'error_url' => 'https://barkeelu.test/error',
        ]);

        $this->assertSame(ProviderInitiationStatus::SENT_UNKNOWN, $result->status);
    }

    public function test_incomplete_success_response_is_sent_unknown(): void
    {
        [$payment] = $this->payment();
        config([
            'services.wave.api_key' => 'wave-api-key',
            'services.wave.request_signing_secret' => 'request-secret',
            'services.wave.base_url' => 'https://api.wave.test',
        ]);
        Http::fake(['https://api.wave.test/v1/checkout/sessions' => Http::response(['id' => 'session-1'])]);

        $result = app(WaveGateway::class)->initiate($payment, [
            'payer_mobile' => '+221771234567',
            'success_url' => 'https://barkeelu.test/success',
            'error_url' => 'https://barkeelu.test/error',
        ]);

        $this->assertSame(ProviderInitiationStatus::SENT_UNKNOWN, $result->status);
    }

    public function test_deterministic_client_errors_and_local_configuration_failure_are_not_sent(): void
    {
        [$payment] = $this->payment();
        config([
            'services.wave.api_key' => 'wave-api-key',
            'services.wave.request_signing_secret' => 'request-secret',
            'services.wave.base_url' => 'https://api.wave.test',
        ]);

        foreach ([400, 401, 403, 422] as $status) {
            Http::fake(['https://api.wave.test/v1/checkout/sessions' => Http::response([], $status)]);
            $result = app(WaveGateway::class)->initiate($payment, [
                'payer_mobile' => '+221771234567',
                'success_url' => 'https://barkeelu.test/success',
                'error_url' => 'https://barkeelu.test/error',
            ]);
            $this->assertSame(ProviderInitiationStatus::NOT_SENT, $result->status, "HTTP {$status}");
        }

        config(['services.wave.api_key' => null]);
        Http::fake();
        $result = app(WaveGateway::class)->initiate($payment, [
            'payer_mobile' => '+221771234567',
            'success_url' => 'https://barkeelu.test/success',
            'error_url' => 'https://barkeelu.test/error',
        ]);
        $this->assertSame(ProviderInitiationStatus::NOT_SENT, $result->status);
        Http::assertNothingSent();
    }

    public function test_webhook_signature_accepts_rotation_rejects_stale_and_deduplicates_official_wave_event_id(): void
    {
        config(['services.wave.webhook_signing_secret' => 'webhook-secret']);
        $raw = '{"id":"evt-wave-1","type":"checkout.session.updated","data":{}}';
        $timestamp = (string) now()->timestamp;
        $valid = hash_hmac('sha256', $timestamp.$raw, 'webhook-secret');
        $wave = app(WaveCheckoutService::class);

        $this->assertTrue($wave->signatureIsValid($raw, "t={$timestamp},v1=".str_repeat('0', 64).",v1={$valid}"));
        $this->assertFalse($wave->signatureIsValid($raw, 't='.(now()->subMinutes(6)->timestamp).",v1={$valid}"));

        [, $account] = $this->payment();
        Queue::fake();
        $ingress = app(WebhookIngressService::class);
        $first = $ingress->receive($account, $raw, ['Wave-Signature' => "t={$timestamp},v1={$valid}"], true);
        $second = $ingress->receive($account, $raw, [], true);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('evt-wave-1', $first->provider_event_id);
        $this->assertSame('checkout.session.updated', $first->event_type);
    }

    public function test_mapper_enforces_account_amount_currency_and_maps_only_documented_terminal_success(): void
    {
        [$payment, $account] = $this->payment();
        $payment->update([
            'provider_checkout_session_id' => 'session-map',
            'provider_client_reference' => $payment->internal_reference,
        ]);
        $mapper = app(WaveWebhookMapper::class);

        $this->assertSame('PENDING', $mapper->map($this->payload($payment, 'open', 'cancelled'), $account->id)['event']['provider_status']);
        $this->assertSame('PAID', $mapper->map($this->payload($payment, 'complete', 'succeeded'), $account->id)['event']['provider_status']);
        $this->assertSame('EXPIRED', $mapper->map($this->payload($payment, 'expired', 'processing'), $account->id)['event']['provider_status']);
        $this->assertSame('UNKNOWN', $mapper->map($this->payload($payment, 'complete', 'cancelled'), $account->id)['event']['provider_status']);

        $this->expectException(DomainException::class);
        $mapper->map($this->payload($payment, 'complete', 'succeeded', ['data' => ['amount' => '106']]), $account->id);
    }

    public function test_official_minimal_payment_failed_marks_only_correlated_payment_failed(): void
    {
        [$payment, $account] = $this->payment();
        $payment->update([
            'provider_checkout_session_id' => 'session-failed',
            'provider_client_reference' => $payment->internal_reference,
        ]);
        $payload = [
            'id' => 'evt-payment-failed',
            'type' => 'checkout.session.payment_failed',
            'data' => [
                'id' => 'session-failed',
                'checkout_status' => 'failed',
                'payment_status' => 'failed',
                'last_error' => ['code' => 'payer_cancelled'],
            ],
        ];

        $mapped = app(WaveWebhookMapper::class)->map($payload, $account->id);
        $this->assertSame('FAILED', $mapped['event']['provider_status']);
        $this->assertSame($payment->id, $mapped['payment']->id);
        app(PaymentService::class)->applyProviderState($mapped['payment'], $mapped['event']);

        $this->assertSame(PaymentStatus::FAILED, $payment->refresh()->status);
        $this->assertSame(DonationStatus::PENDING, $payment->donation->refresh()->status);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
    }

    public function test_minimal_payment_failed_without_correlated_payment_is_rejected(): void
    {
        [, $account] = $this->payment();

        $this->expectException(ModelNotFoundException::class);
        app(WaveWebhookMapper::class)->map([
            'id' => 'evt-payment-failed-missing',
            'type' => 'checkout.session.payment_failed',
            'data' => ['id' => 'unknown-session', 'checkout_status' => 'failed', 'payment_status' => 'failed'],
        ], $account->id);
    }

    public function test_valid_test_event_is_deduplicated_and_has_no_business_effect(): void
    {
        [$payment, $account] = $this->payment();
        config(['services.wave.webhook_signing_secret' => 'webhook-secret']);
        $payload = ['id' => 'evt-wave-test', 'type' => 'test.test_event', 'data' => []];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.$raw, 'webhook-secret');
        $valid = app(WaveCheckoutService::class)->signatureIsValid($raw, "t={$timestamp},v1={$signature}");
        Queue::fake();

        $ingress = app(WebhookIngressService::class);
        $event = $ingress->receive($account, $raw, ['Wave-Signature' => "t={$timestamp},v1={$signature}"], $valid);
        $duplicate = $ingress->receive($account, $raw, ['Wave-Signature' => "t={$timestamp},v1={$signature}"], $valid);
        $this->assertSame($event->id, $duplicate->id);
        Queue::assertPushed(ProcessWebhookEvent::class, 2);

        $job = new ProcessWebhookEvent($event->id);
        $job->handle(app(PaymentService::class));
        $job->handle(app(PaymentService::class));

        $this->assertSame(WebhookEventStatus::PROCESSED, $event->refresh()->status);
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
        $this->assertSame(DonationStatus::PENDING, $payment->donation->refresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('donations', 1);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
    }

    public function test_invalid_signature_test_event_is_ignored_without_business_effect(): void
    {
        [$payment, $account] = $this->payment();
        config(['services.wave.webhook_signing_secret' => 'webhook-secret']);
        $raw = json_encode(['id' => 'evt-wave-test-invalid', 'type' => 'test.test_event', 'data' => []], JSON_THROW_ON_ERROR);
        $valid = app(WaveCheckoutService::class)->signatureIsValid($raw, 't='.now()->timestamp.',v1='.str_repeat('0', 64));

        $event = app(WebhookIngressService::class)->receive($account, $raw, [], $valid);

        $this->assertFalse($valid);
        $this->assertSame(WebhookEventStatus::IGNORED, $event->status);
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
    }

    public function test_webhook_route_rejects_invalid_signature_after_recording_ignored_event_without_dispatching_a_job(): void
    {
        [$payment, $account] = $this->payment();
        Queue::fake();

        $response = $this->waveWebhook('WAVE', ['id' => 'evt-wave-route-invalid', 'type' => 'test.test_event', 'data' => []], str_repeat('0', 64));

        $response->assertUnauthorized();
        $this->assertDatabaseHas('webhook_events', [
            'provider_account_id' => $account->id,
            'provider_event_id' => 'evt-wave-route-invalid',
            'signature_valid' => false,
            'status' => WebhookEventStatus::IGNORED->value,
        ]);
        Queue::assertNothingPushed();
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
        $this->assertSame(DonationStatus::PENDING, $payment->donation->refresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('donations', 1);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_valid_wave_healthcheck_is_processed_without_job_or_financial_effect(): void
    {
        [$payment, $account] = $this->payment();
        Queue::fake();

        $response = $this->waveWebhook('WAVE', ['type' => 'healthcheck']);

        $response->assertAccepted();
        $event = WebhookEvent::query()->sole();
        $this->assertSame($account->id, $event->provider_account_id);
        $this->assertSame(WebhookEventStatus::PROCESSED, $event->status);
        $this->assertNotNull($event->processed_at);
        Queue::assertNothingPushed();
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
        $this->assertSame(DonationStatus::PENDING, $payment->donation->refresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('donations', 1);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertDatabaseCount('ledger_transactions', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_webhook_route_resolves_lowercase_wave_to_single_active_uppercase_account(): void
    {
        [$payment, $account] = $this->payment();
        Queue::fake();

        $response = $this->waveWebhook('wave', ['id' => 'evt-wave-route-single', 'type' => 'test.test_event', 'data' => []]);

        $response->assertAccepted();
        $this->assertDatabaseHas('webhook_events', ['provider_account_id' => $account->id, 'provider_event_id' => 'evt-wave-route-single']);
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
    }

    public function test_webhook_route_rejects_ambiguous_wave_accounts_without_business_effect(): void
    {
        [$payment] = $this->payment();
        ProviderAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'provider' => 'wave',
            'name' => 'Second Wave account',
            'environment' => 'TEST',
            'currency' => 'XOF',
            'is_active' => true,
        ]);
        Queue::fake();

        $response = $this->waveWebhook('WAVE', ['id' => 'evt-wave-route-ambiguous', 'type' => 'test.test_event', 'data' => []]);

        $response->assertNotFound();
        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertSame(PaymentStatus::CREATED, $payment->refresh()->status);
        $this->assertSame(0, AppliedFee::query()->count());
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
    }

    public function test_webhook_route_rejects_when_no_wave_account_is_active(): void
    {
        Queue::fake();

        $response = $this->waveWebhook('WAVE', ['id' => 'evt-wave-route-missing', 'type' => 'test.test_event', 'data' => []]);

        $response->assertNotFound();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    public function test_gateway_resolver_accepts_canonical_and_lowercase_wave_provider(): void
    {
        $resolver = app(ProviderGatewayResolver::class);

        foreach (['WAVE', 'wave'] as $provider) {
            $this->assertInstanceOf(WaveGateway::class, $resolver->for(new ProviderAccount(['provider' => $provider])));
        }
    }

    public function test_webhook_route_does_not_use_auth_token_throttle(): void
    {
        $this->payment();
        Queue::fake();
        $payload = ['id' => 'evt-wave-throttle-auth', 'type' => 'test.test_event', 'data' => []];

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->waveWebhook('WAVE', $payload)->assertAccepted();
        }
    }

    public function test_wave_webhook_throttle_returns_429_only_after_dedicated_limit(): void
    {
        $this->payment();
        Queue::fake();
        $payload = ['id' => 'evt-wave-throttle-limit', 'type' => 'test.test_event', 'data' => []];

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->waveWebhook('WAVE', $payload)->assertAccepted();
        }

        $this->waveWebhook('WAVE', $payload)->assertStatus(429);
    }

    public function test_paid_is_irreversible_and_unknown_is_not_downgraded_by_an_open_checkout(): void
    {
        [$payment] = $this->payment();
        $service = app(PaymentService::class);
        $service->applyProviderState($payment, $this->event($payment, 'PAID'));
        $service->applyProviderState($payment, $this->event($payment, 'PENDING'));
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);

        [, , $unknown] = $this->paymentWithDonation();
        $service->applyProviderState($unknown, $this->event($unknown, 'TIMEOUT'));
        $service->applyProviderState($unknown, $this->event($unknown, 'PENDING'));
        $this->assertSame(PaymentStatus::UNKNOWN, $unknown->refresh()->status);
    }

    public function test_expired_payer_mobile_is_encrypted_hidden_and_purged_only_after_thirty_days(): void
    {
        [$payment] = $this->payment();
        $payment->update(['payer_mobile_encrypted' => '+221771234567', 'status' => PaymentStatus::EXPIRED, 'updated_at' => now()->subDays(31)]);
        $raw = DB::table('payments')->where('id', $payment->id)->value('payer_mobile_encrypted');
        $this->assertNotSame('+221771234567', $raw);
        $this->assertSame('+221771234567', $payment->refresh()->payer_mobile_encrypted);
        $this->assertStringNotContainsString('+221771234567', $payment->refresh()->toJson());

        $this->artisan(PurgeExpiredPayerMobiles::class)->assertSuccessful();
        $this->assertNull($payment->refresh()->payer_mobile_encrypted);

        [, , $recent] = $this->paymentWithDonation();
        $recent->update(['payer_mobile_encrypted' => '+221781234567', 'status' => PaymentStatus::EXPIRED, 'updated_at' => now()->subDays(29)]);
        $this->artisan(PurgeExpiredPayerMobiles::class)->assertSuccessful();
        $this->assertSame('+221781234567', $recent->refresh()->payer_mobile_encrypted);
    }

    private function payment(): array
    {
        [, $account, $payment] = $this->paymentWithDonation();

        return [$payment, $account];
    }

    private function configureWave(): void
    {
        config([
            'services.wave.api_key' => 'wave-api-key',
            'services.wave.request_signing_secret' => 'request-secret',
            'services.wave.base_url' => 'https://api.wave.test',
        ]);
    }

    private function checkoutPayload(Payment $payment, string $id, string $checkoutStatus = 'complete', string $paymentStatus = 'succeeded'): array
    {
        return [
            'id' => $id,
            'client_reference' => $payment->internal_reference,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'checkout_status' => $checkoutStatus,
            'payment_status' => $paymentStatus,
            'transaction_id' => 'transaction-'.$id,
        ];
    }

    private function paymentWithDonation(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'wave-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 1_000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'WAVE', 'name' => 'Wave test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = $this->createPendingConfirmedDonation($campaign, null, 'wave-donation-'.Str::uuid(), [
            'payoutProvisionAmount' => 1,
            'totalPayableAmount' => 105,
        ]);
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 105, 'currency' => 'XOF', 'idempotency_key' => 'wave-payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);

        return [$donation, $account, $payment];
    }

    private function payload(Payment $payment, string $checkoutStatus, string $paymentStatus, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'evt-'.Str::uuid(),
            'type' => 'checkout.session.updated',
            'data' => [
                'id' => 'session-map',
                'client_reference' => $payment->internal_reference,
                'amount' => (string) $payment->amount,
                'currency' => 'XOF',
                'checkout_status' => $checkoutStatus,
                'payment_status' => $paymentStatus,
                'transaction_id' => 'transaction-1',
            ],
        ], $overrides);
    }

    private function event(Payment $payment, string $status): array
    {
        return ['provider_account_id' => $payment->provider_account_id, 'internal_reference' => $payment->internal_reference, 'amount' => $payment->amount, 'currency' => $payment->currency, 'provider_status' => $status, 'provider_payment_id' => 'provider-'.$payment->id];
    }

    private function waveWebhook(string $provider, array $payload, ?string $signature = null)
    {
        config(['services.wave.webhook_signing_secret' => 'webhook-secret']);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature ??= hash_hmac('sha256', $timestamp.$raw, 'webhook-secret');

        return $this->call('POST', '/api/v1/webhooks/'.$provider, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WAVE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $raw);
    }
}
