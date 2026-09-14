<?php

namespace Tests\Feature\Payments;

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
use App\Services\Donations\DonationService;
use App\Services\Payments\PaymentService;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_postgresql_processes_create_one_paid_effect(): void
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'c-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 1000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST_PROVIDER', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = app(DonationService::class)->create($campaign, $user, ['nominal_amount' => 100, 'currency' => 'XOF', 'idempotency_key' => 'donation-'.Str::uuid()]);
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $event = ['provider_account_id' => $account->id, 'internal_reference' => $payment->internal_reference, 'amount' => 104, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'provider-'.$payment->id];
        $workers = [$this->startWorker($payment->id, $event), $this->startWorker($payment->id, $event)];
        foreach ($workers as $worker) {
            $this->assertSame(0, $this->finishWorker($worker));
        }
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 3);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    private function startWorker(int $paymentId, array $event): array
    {
        $command = sprintf('"%s" "%s" "%s" "%s"', PHP_BINARY, base_path('tests/Fixtures/Payments/concurrent_payment_success.php'), $paymentId, base64_encode(json_encode($event, JSON_THROW_ON_ERROR)));
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    private function finishWorker(array $worker): int
    {
        [$process, $pipes] = $worker;
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}
