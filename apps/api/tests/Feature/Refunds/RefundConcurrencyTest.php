<?php

namespace Tests\Feature\Refunds;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\PaymentService;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class RefundConcurrencyTest extends TestCase
{
    use CreatesConfirmedDonations;
    use DatabaseMigrations;

    public function test_two_processes_cannot_over_reserve(): void
    {
        $this->seed(FinancialFoundationSeeder::class);
        $u = User::factory()->create();
        $b = Beneficiary::create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $u->id]);
        $c = Campaign::create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $u->id, 'created_by_user_id' => $u->id, 'beneficiary_id' => $b->id, 'title' => 'C', 'slug' => 'c-'.Str::random(8), 'description' => 'D', 'goal_amount' => 20000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $a = ProviderAccount::create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST', 'name' => 'T', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $d = $this->createPendingConfirmedDonation($c, $u, 'd-'.Str::uuid(), [
            'nominalAmount' => 10_000,
            'platformFeeAmount' => 400,
            'totalPayableAmount' => 10_400,
        ]);
        $p = app(PaymentService::class)->create($d, $a, ['amount' => 10400, 'currency' => 'XOF', 'idempotency_key' => 'p-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($p);
        app(PaymentService::class)->applyProviderState($p, ['provider_account_id' => $a->id, 'internal_reference' => $p->internal_reference, 'amount' => 10400, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'x']);
        $workers = [$this->worker($p, $u, 'r-a'), $this->worker($p, $u, 'r-b')];
        $results = array_map(fn ($w) => $this->finish($w), $workers);
        $p = $p->refresh();
        $this->assertSame(1, DB::table('refunds')->count());
        $this->assertSame(7000, $p->reserved_refund_amount);
        $this->assertSame(0, $p->executed_refund_amount);
        $this->assertSame(2, DB::table('ledger_transactions')->count());
        $this->assertSame(5, DB::table('ledger_entries')->count());
        $this->assertSame(2, DB::table('outbox_events')->count());
        $this->assertContains(0, array_column($results, 'code'));
        $this->assertContains(10, array_column($results, 'code'));
    }

    private function worker(Payment $p, User $u, string $key): array
    {
        $cmd = '"'.PHP_BINARY.'" "'.base_path('tests/Fixtures/Refunds/concurrent_refund.php').'" '.$p->id.' '.$u->id.' '.$key;
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

        return [$proc, $pipes];
    }

    private function finish(array $w): array
    {
        [$p,$pipes] = $w;
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['code' => proc_close($p), 'output' => $out.$err];
    }
}
