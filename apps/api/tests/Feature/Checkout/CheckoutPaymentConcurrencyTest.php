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
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use App\Services\Payments\ProviderGatewayResolver;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Fixtures\Checkout\CountingProviderGateway;
use Tests\TestCase;

class CheckoutPaymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_postgresql_processes_initiate_one_payment_and_one_provider_call(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $campaign = $session->campaign;
        $beneficiaryId = $campaign->beneficiary_id;
        $ownerId = $campaign->owner_user_id;
        $campaignId = $campaign->id;
        DB::connection()->commit();

        $workers = [$this->startWorker($session->id, $account->id), $this->startWorker($session->id, $account->id)];

        try {
            $outputs = array_map(fn (array $worker): string => $this->finishWorker($worker), $workers);

            $this->assertSame(1, CheckoutSession::query()->whereKey($session->id)->value('last_payment_id') !== null ? 1 : 0);
            $this->assertSame(1, $session->donation()->count());
            $this->assertSame(1, DB::table('payments')->where('donation_id', $session->donation_id)->count());
            $this->assertCount(1, array_unique(array_filter($outputs)), implode(' | ', $outputs));
            $this->assertSame(1, DB::table('checkout_test_provider_calls')->count());
            $this->assertSame(CheckoutStatus::PAYMENT_PENDING, CheckoutSession::query()->findOrFail($session->id)->status);
        } finally {
            DB::table('checkout_test_provider_calls')->delete();
            DB::table('payments')->where('donation_id', $session->donation_id)->delete();
            DB::table('donations')->where('id', $session->donation_id)->delete();
            DB::table('checkout_sessions')->where('id', $session->id)->delete();
            DB::table('provider_accounts')->where('id', $account->id)->delete();
            DB::table('campaigns')->where('id', $campaignId)->delete();
            DB::table('beneficiaries')->where('id', $beneficiaryId)->delete();
            DB::table('users')->where('id', $ownerId)->delete();
            DB::statement('DROP TABLE checkout_test_provider_calls');
        }
    }

    public function test_same_key_with_different_content_conflicts_without_second_payment_or_provider_call(): void
    {
        [$session, $account] = $this->confirmedCheckout();
        $resolver = Mockery::mock(ProviderGatewayResolver::class);
        $resolver->shouldReceive('for')->andReturn(new CountingProviderGateway);
        $this->app->instance(ProviderGatewayResolver::class, $resolver);
        $service = app(CheckoutPaymentService::class);
        $input = $this->paymentInput('payment-conflict');

        try {
            $service->initiate($session, $account, $input);
            $this->expectException(DomainException::class);
            $service->initiate($session->refresh(), $account, array_merge($input, ['payer_mobile' => '+221780000000']));
        } finally {
            $this->assertSame(1, DB::table('payments')->where('donation_id', $session->donation_id)->count());
            $this->assertSame(1, DB::table('checkout_test_provider_calls')->count());
        }
    }

    private function confirmedCheckout(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        DB::statement('CREATE TABLE checkout_test_provider_calls (payment_id bigint NOT NULL, created_at timestamp(0) NOT NULL)');
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Concurrency beneficiary', 'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
        $campaign = Campaign::query()->create([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Concurrency checkout', 'slug' => 'concurrency-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC,
        ]);
        $account = ProviderAccount::query()->create([
            'public_id' => (string) Str::uuid(), 'provider' => 'WAVE', 'name' => 'Concurrent Wave',
            'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true,
        ]);
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'checkout-concurrent-'.Str::uuid(),
        ]);
        $session = app(CheckoutQuoteService::class)->quote($session, [
            'name' => 'Awa', 'email' => 'awa@example.test', 'phone' => '+221770000000',
            'is_anonymous' => false, 'show_name' => true, 'show_amount' => false,
        ]);
        $session = app(CheckoutConfirmationService::class)->confirm($session, ['idempotency_key' => 'confirm-concurrent-'.Str::uuid()]);

        return [$session, $account];
    }

    private function startWorker(int $sessionId, int $accountId): array
    {
        $command = sprintf('"%s" "%s" "%s" "%s"', PHP_BINARY, base_path('tests/Fixtures/Checkout/concurrent_payment_initiation.php'), $sessionId, $accountId);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    private function finishWorker(array $worker): string
    {
        [$process, $pipes] = $worker;
        $output = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $this->assertSame(0, $exit, $error);

        return $output;
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
