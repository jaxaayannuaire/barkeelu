<?php

namespace Tests\Feature\Payouts;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\PayoutStatus;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\LedgerAccount;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Payouts\PayoutService;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class PayoutServiceTest extends TestCase
{
    use CreatesConfirmedDonations;
    use RefreshDatabase;

    public function test_request_is_idempotent_and_requires_finance_operator_permission(): void
    {
        [$campaign, $account, $operator] = $this->context();
        $service = app(PayoutService::class);
        $input = $this->input('payout-request', 60);
        $payout = $service->request($campaign, $account, $operator, $input);

        $this->assertSame($payout->id, $service->request($campaign, $account, $operator, $input)->id);
        $this->assertDatabaseCount('payouts', 1);
        $this->assertSame(['account' => 'masked-1'], $payout->destination_snapshot);

        try {
            $service->request($campaign, $account, $operator, $this->input('payout-request', 61));
            $this->fail('Un contenu différent avec la même clé doit être rejeté.');
        } catch (DomainException) {
            $this->assertDatabaseCount('payouts', 1);
        }

        $this->expectException(DomainException::class);
        $service->request($campaign, $account, User::factory()->create(), $this->input('not-operator', 60));
    }

    public function test_self_approval_is_rejected(): void
    {
        [$campaign, $account, $operator] = $this->context();
        $operator->givePermissionTo('finance.approve');
        $payout = app(PayoutService::class)->request($campaign, $account, $operator, $this->input('self-approval', 60));

        $this->expectException(DomainException::class);
        app(PayoutService::class)->approve($payout, $operator);
    }

    public function test_approver_reserves_then_executes_and_release_restores_campaign_payable(): void
    {
        [$campaign, $account, $operator, $approver] = $this->context();
        $service = app(PayoutService::class);
        $campaignPayable = LedgerAccount::query()->where('code', 'CAMPAIGN_PAYABLE')->value('id');
        $payoutReserved = LedgerAccount::query()->where('code', 'PAYOUT_RESERVED')->value('id');
        $providerFunds = LedgerAccount::query()->where('code', 'PROVIDER_FUNDS')->value('id');

        $payout = $service->approve($service->request($campaign, $account, $operator, $this->input('payout-execute', 60)), $approver);
        $this->assertSame(PayoutStatus::RESERVED, $payout->status);
        $this->assertSame($approver->id, $payout->approved_by_user_id);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => $campaignPayable, 'campaign_id' => $campaign->id, 'direction' => 'DEBIT', 'amount' => 60]);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => $payoutReserved, 'campaign_id' => $campaign->id, 'direction' => 'CREDIT', 'amount' => 60]);

        $payout = $service->providerState($payout, 'SUCCEEDED');
        $this->assertSame(PayoutStatus::SUCCEEDED, $payout->status);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => $payoutReserved, 'campaign_id' => $campaign->id, 'direction' => 'DEBIT', 'amount' => 60]);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => $providerFunds, 'campaign_id' => $campaign->id, 'direction' => 'CREDIT', 'amount' => 60]);

        $released = $service->approve($service->request($campaign, $account, $operator, $this->input('payout-release', 30)), $approver);
        $released = $service->providerState($released, 'FAILED');
        $this->assertSame(PayoutStatus::CANCELLED, $released->status);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => $payoutReserved, 'campaign_id' => $campaign->id, 'direction' => 'DEBIT', 'amount' => 30]);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => $campaignPayable, 'campaign_id' => $campaign->id, 'direction' => 'CREDIT', 'amount' => 30]);
    }

    public function test_over_reservation_timeout_and_critical_update_are_controlled(): void
    {
        [$campaign, $account, $operator, $approver] = $this->context();
        $service = app(PayoutService::class);
        $reserved = $service->approve($service->request($campaign, $account, $operator, $this->input('payout-reserved', 60)), $approver);

        $updated = $service->updateCritical($reserved, ['amount' => 50, 'destination_snapshot' => ['account' => 'masked-2']]);
        $this->assertSame(PayoutStatus::PENDING_APPROVAL, $updated->status);
        $this->assertNull($updated->approved_by_user_id);
        $this->assertNull($updated->approved_at);
        $this->assertSame(['account' => 'masked-2'], $updated->destination_snapshot);
        $this->assertSame(['account' => 'masked-1'], $updated->approved_destination_snapshot);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'CAMPAIGN_PAYABLE')->value('id'), 'campaign_id' => $campaign->id, 'direction' => 'CREDIT', 'amount' => 60]);

        $timeout = $service->providerState($service->approve($updated, $approver), 'TIMEOUT');
        $this->assertSame(PayoutStatus::UNKNOWN, $timeout->status);

        foreach ([
            ['currency' => 'XOF'],
            ['beneficiary_id' => $campaign->beneficiary_id],
            ['provider_account_id' => $account->id],
        ] as $index => $change) {
            $candidate = $service->approve($service->request($campaign, $account, $operator, $this->input('payout-critical-'.$index, 10)), $approver);
            $invalidated = $service->updateCritical($candidate, $change);
            $this->assertSame(PayoutStatus::PENDING_APPROVAL, $invalidated->status);
            $this->assertNull($invalidated->approved_by_user_id);
        }

        $tooLarge = $service->request($campaign, $account, $operator, $this->input('payout-over', 101));
        $this->expectException(DomainException::class);
        $service->approve($tooLarge, $approver);
    }

    private function input(string $key, int $amount): array
    {
        return [
            'amount' => $amount,
            'currency' => 'XOF',
            'idempotency_key' => $key,
            'destination_snapshot' => ['account' => 'masked-1'],
        ];
    }

    private function context(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $operator = User::factory()->create();
        $approver = User::factory()->create();
        Permission::findOrCreate('finance.operate', 'web');
        Permission::findOrCreate('finance.approve', 'web');
        $operator->givePermissionTo('finance.operate');
        $approver->givePermissionTo('finance.approve');
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $operator->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $operator->id, 'created_by_user_id' => $operator->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'c-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 1000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = $this->createPendingConfirmedDonation($campaign, $operator, 'donation-'.Str::uuid());
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
        app(PaymentService::class)->applyProviderState($payment, ['provider_account_id' => $account->id, 'internal_reference' => $payment->internal_reference, 'amount' => 104, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'payment-provider']);

        return [$campaign, $account, $operator, $approver];
    }
}
