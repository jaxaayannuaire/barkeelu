<?php

namespace Tests\Feature\Checkout;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\FeeType;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\FeePolicy;
use App\Models\User;
use App\Services\Checkout\CheckoutQuoteService;
use App\Services\Checkout\CheckoutSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutConfirmationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_postgresql_processes_confirm_one_checkout_into_one_donation(): void
    {
        DB::connection()->commit();
        [$campaign] = $this->campaignContext();
        FeePolicy::query()->create([
            'public_id' => (string) Str::uuid(), 'code' => 'PAYOUT_PROVISION_CONCURRENCY',
            'fee_type' => FeeType::PAYOUT_PROVISION, 'rate_bps' => 50, 'currency' => 'XOF',
            'effective_from' => Carbon::now()->subMinute(), 'active' => true,
        ]);
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'checkout-confirm-concurrent',
        ]);
        $session = app(CheckoutQuoteService::class)->quote($session, [
            'name' => 'Awa', 'email' => 'awa@example.test', 'phone' => '+221770000000',
            'is_anonymous' => false, 'show_name' => true, 'show_amount' => false,
        ]);
        DB::connection()->beginTransaction();
        $workers = [$this->startWorker($session->id), $this->startWorker($session->id)];

        try {
            foreach ($workers as $worker) {
                $this->assertSame(0, $this->finishWorker($worker));
            }

            $this->assertSame(1, DB::table('donations')->where('idempotency_key', 'checkout-confirmation:'.$session->public_id)->count());
            $this->assertSame(1, DB::table('checkout_sessions')->where('id', $session->id)->whereNotNull('donation_id')->count());
        } finally {
            DB::table('donations')->where('idempotency_key', 'checkout-confirmation:'.$session->public_id)->delete();
            DB::table('checkout_sessions')->where('id', $session->id)->delete();
            DB::table('fee_policies')->where('code', 'PAYOUT_PROVISION_CONCURRENCY')->delete();
            DB::table('campaigns')->where('id', $campaign->id)->delete();
            DB::table('beneficiaries')->where('id', $campaign->beneficiary_id)->delete();
            DB::table('users')->where('id', $campaign->owner_user_id)->delete();
            DB::connection()->commit();
            DB::connection()->beginTransaction();
        }
    }

    public function test_two_different_confirmation_keys_yield_one_success_and_one_conflict(): void
    {
        DB::connection()->commit();
        [$campaign] = $this->campaignContext();
        $session = app(CheckoutSessionService::class)->create($campaign, null, [
            'currency' => 'XOF', 'nominal_amount' => 10_000, 'idempotency_key' => 'checkout-confirm-different-keys',
        ]);
        $session = app(CheckoutQuoteService::class)->quote($session, [
            'name' => 'Awa', 'email' => 'awa@example.test', 'phone' => '+221770000000',
            'is_anonymous' => false, 'show_name' => true, 'show_amount' => false,
        ]);
        DB::connection()->beginTransaction();
        $workers = [$this->startWorker($session->id, 'confirm-a'), $this->startWorker($session->id, 'confirm-b')];

        try {
            $results = array_map(fn (array $worker): int => $this->finishWorker($worker), $workers);
            sort($results);

            $this->assertSame([0, 42], $results);
            $this->assertSame(1, DB::table('donations')->where('idempotency_key', 'checkout-confirmation:'.$session->public_id)->count());
        } finally {
            DB::table('donations')->where('idempotency_key', 'checkout-confirmation:'.$session->public_id)->delete();
            DB::table('checkout_sessions')->where('id', $session->id)->delete();
            DB::table('campaigns')->where('id', $campaign->id)->delete();
            DB::table('beneficiaries')->where('id', $campaign->beneficiary_id)->delete();
            DB::table('users')->where('id', $campaign->owner_user_id)->delete();
            DB::connection()->commit();
            DB::connection()->beginTransaction();
        }
    }

    private function campaignContext(): array
    {
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL,
            'display_name' => 'Bénéficiaire', 'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
        $campaign = Campaign::query()->create([
            'public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id,
            'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id,
            'title' => 'Campagne confirmation', 'slug' => 'confirmation-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED->value, 'fundraising_status' => CampaignFundraisingStatus::OPEN->value,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE->value, 'visibility' => CampaignVisibility::PUBLIC->value,
        ]);

        return [$campaign, $user];
    }

    private function startWorker(int $sessionId, string $key = 'confirm-concurrent'): array
    {
        $command = sprintf('"%s" "%s" "%s" "%s"', PHP_BINARY, base_path('tests/Fixtures/Checkout/concurrent_checkout_confirmation.php'), $sessionId, $key);
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
