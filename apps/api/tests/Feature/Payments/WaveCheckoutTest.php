<?php

namespace Tests\Feature\Payments;

use App\Console\Commands\PurgeExpiredPayerMobiles;
use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\PaymentStatus;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Donations\DonationService;
use App\Services\Payments\PaymentService;
use App\Services\Payments\WaveCheckoutService;
use App\Services\Payments\WaveWebhookMapper;
use App\Services\Webhooks\WebhookIngressService;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WaveCheckoutTest extends TestCase
{
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

    public function test_legacy_retry_route_is_disabled_for_the_checkout_facade(): void
    {
        [$donation, , $payment] = $this->paymentWithDonation();
        Http::fake();
        $this->withSession($this->privateSession($donation, $payment))
            ->post(route('donations.retry', [$donation->public_id, $payment->public_id]))
            ->assertStatus(410);
        $this->assertDatabaseCount('payments', 1);
    }

    private function payment(): array
    {
        [, $account, $payment] = $this->paymentWithDonation();

        return [$payment, $account];
    }

    private function paymentWithDonation(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'wave-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 1_000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'WAVE', 'name' => 'Wave test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = app(DonationService::class)->create($campaign, null, ['nominal_amount' => 100, 'currency' => 'XOF', 'idempotency_key' => 'wave-donation-'.Str::uuid()]);
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 105, 'currency' => 'XOF', 'idempotency_key' => 'wave-payment-'.Str::uuid()]);

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

    private function privateSession($donation, Payment $payment): array
    {
        return [
            'donation_flow.'.$donation->campaign->slug => ['token' => 'private-flow-token', 'expires_at' => now()->addHour()->timestamp],
            'donation_payment.'.$payment->public_id => 'private-flow-token',
        ];
    }
}
