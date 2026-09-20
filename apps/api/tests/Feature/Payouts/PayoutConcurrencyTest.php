<?php

namespace Tests\Feature\Payouts;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class PayoutConcurrencyTest extends TestCase
{
    use CreatesConfirmedDonations;
    use DatabaseMigrations;

    public function test_two_real_postgresql_processes_cannot_over_reserve_a_campaign(): void
    {
        $this->seed(FinancialFoundationSeeder::class);
        [$campaign, $account, $operator, $approver] = $this->fundedContext();

        $results = array_map(
            fn (array $worker): array => $this->finish($worker),
            [
                $this->worker($campaign, $account, $operator, $approver, 'payout-concurrent-a'),
                $this->worker($campaign, $account, $operator, $approver, 'payout-concurrent-b'),
            ],
        );

        $campaignPayable = $this->balance($campaign->id, 'CAMPAIGN_PAYABLE');
        $payoutReserved = $this->balance($campaign->id, 'PAYOUT_RESERVED');

        $this->assertSame(2, DB::table('payouts')->count());
        $this->assertSame(1, DB::table('payouts')->where('status', 'RESERVED')->count());
        $this->assertSame(2, DB::table('ledger_transactions')->count());
        $this->assertSame(5, DB::table('ledger_entries')->count());
        $this->assertSame(2, DB::table('outbox_events')->count());
        $this->assertSame(3_000, $campaignPayable);
        $this->assertSame(7_000, $payoutReserved);
        $this->assertContains(0, array_column($results, 'code'));
        $this->assertContains(10, array_column($results, 'code'));
    }

    private function fundedContext(): array
    {
        $operator = User::factory()->create();
        $approver = User::factory()->create();
        Permission::findOrCreate('finance.operate', 'web');
        Permission::findOrCreate('finance.approve', 'web');
        $operator->givePermissionTo('finance.operate');
        $approver->givePermissionTo('finance.approve');
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $operator->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $operator->id, 'created_by_user_id' => $operator->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'c-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 20_000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = $this->createPendingConfirmedDonation($campaign, $operator, 'donation-'.Str::uuid(), [
            'nominalAmount' => 10_000,
            'platformFeeAmount' => 400,
            'totalPayableAmount' => 10_400,
        ]);
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 10_400, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
        app(PaymentService::class)->applyProviderState($payment, ['provider_account_id' => $account->id, 'internal_reference' => $payment->internal_reference, 'amount' => 10_400, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'provider-payment']);

        return [$campaign, $account, $operator, $approver];
    }

    private function worker(Campaign $campaign, ProviderAccount $account, User $operator, User $approver, string $key): array
    {
        $command = sprintf(
            '"%s" "%s" %d %d %d %s %d %d',
            PHP_BINARY,
            base_path('tests/Fixtures/Payouts/concurrent_payout.php'),
            $campaign->id,
            $account->id,
            $operator->id,
            $key,
            7_000,
            $approver->id,
        );
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

        return [$process, $pipes];
    }

    private function finish(array $worker): array
    {
        [$process, $pipes] = $worker;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($process), 'output' => $output.$error];
    }

    private function balance(int $campaignId, string $accountCode): int
    {
        return (int) DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')
            ->where('ledger_entries.campaign_id', $campaignId)
            ->where('ledger_accounts.code', $accountCode)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'CREDIT' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');
    }
}
