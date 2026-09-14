<?php

namespace Tests\Feature\Payments;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\DonationStatus;
use App\Enums\PaymentStatus;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\LedgerAccount;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Donations\DonationService;
use App\Services\Payments\PaymentService;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_and_payment_creation_are_idempotent_and_conflicts_are_explicit(): void
    {
        [$user, $campaign, $account] = $this->context();
        $donationService = app(DonationService::class);
        $input = ['nominal_amount' => 10000, 'currency' => 'XOF', 'idempotency_key' => 'donation-key'];
        $donation = $donationService->create($campaign, $user, $input);
        $this->assertSame($donation->id, $donationService->create($campaign, $user, $input)->id);
        $this->assertSame(400, $donation->platform_fee_amount);
        $this->expectException(DomainException::class);
        $donationService->create($campaign, $user, array_merge($input, ['nominal_amount' => 10001]));
    }

    public function test_first_and_second_paid_payment_have_distinct_ledger_effects(): void
    {
        [$user, $campaign, $account] = $this->context();
        $donation = app(DonationService::class)->create($campaign, $user, ['nominal_amount' => 10000, 'currency' => 'XOF', 'idempotency_key' => 'donation-paid']);
        $payments = app(PaymentService::class);
        $first = $payments->create($donation, $account, ['amount' => 10400, 'currency' => 'XOF', 'idempotency_key' => 'payment-a']);
        $second = $payments->create($donation, $account, ['amount' => 10400, 'currency' => 'XOF', 'idempotency_key' => 'payment-b']);
        $payments->applyProviderState($first, $this->paidEvent($first));
        $payments->applyProviderState($second, $this->paidEvent($second, 'provider-b'));
        $this->assertSame(DonationStatus::PAID, $donation->refresh()->status);
        $this->assertSame(PaymentStatus::PAID, $second->refresh()->status);
        $this->assertDatabaseCount('ledger_transactions', 2);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'UNAPPLIED_FUNDS')->value('id'), 'amount' => 10400, 'direction' => 'CREDIT']);
        $this->assertSame(10000, $donation->refresh()->nominal_amount);
    }

    public function test_timeout_becomes_unknown_and_invalid_provider_data_is_rejected(): void
    {
        [$user, $campaign, $account] = $this->context();
        $donation = app(DonationService::class)->create($campaign, $user, ['nominal_amount' => 100, 'currency' => 'XOF', 'idempotency_key' => 'donation-timeout']);
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-timeout']);
        app(PaymentService::class)->applyProviderState($payment, array_merge($this->paidEvent($payment), ['provider_status' => 'TIMEOUT']));
        $this->assertSame(PaymentStatus::UNKNOWN, $payment->refresh()->status);
        $this->expectException(DomainException::class);
        app(PaymentService::class)->applyProviderState($payment, array_merge($this->paidEvent($payment), ['amount' => 105]));
    }

    private function context(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'Beneficiary', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'Campaign', 'slug' => 'campaign-'.Str::lower(Str::random(8)), 'description' => 'Description', 'goal_amount' => 100000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST_PROVIDER', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);

        return [$user, $campaign, $account];
    }

    private function paidEvent($payment, string $providerPaymentId = 'provider-a'): array
    {
        return ['provider_account_id' => $payment->provider_account_id, 'internal_reference' => $payment->internal_reference, 'amount' => $payment->amount, 'currency' => $payment->currency, 'provider_status' => 'PAID', 'provider_payment_id' => $providerPaymentId];
    }
}
