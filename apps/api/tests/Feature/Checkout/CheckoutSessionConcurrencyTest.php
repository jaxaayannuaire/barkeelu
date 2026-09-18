<?php

namespace Tests\Feature\Checkout;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutSessionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_postgresql_processes_create_one_checkout_session_for_one_key_and_content(): void
    {
        DB::connection()->commit();
        [$campaign] = $this->campaignContext();
        DB::connection()->beginTransaction();
        $workers = [$this->startWorker($campaign->id), $this->startWorker($campaign->id)];

        try {
            foreach ($workers as $worker) {
                $this->assertSame(0, $this->finishWorker($worker));
            }

            $this->assertDatabaseCount('checkout_sessions', 1);
            $this->assertDatabaseHas('checkout_sessions', [
                'campaign_id' => $campaign->id,
                'idempotency_key' => 'checkout-concurrent',
            ]);
        } finally {
            DB::table('checkout_sessions')->where('campaign_id', $campaign->id)->delete();
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
            'title' => 'Campagne concurrente', 'slug' => 'concurrent-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED->value,
            'fundraising_status' => CampaignFundraisingStatus::OPEN->value,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE->value,
            'visibility' => CampaignVisibility::PUBLIC->value,
        ]);

        return [$campaign, $user];
    }

    private function startWorker(int $campaignId): array
    {
        $command = sprintf('"%s" "%s" "%s"', PHP_BINARY, base_path('tests/Fixtures/Checkout/concurrent_checkout_creation.php'), $campaignId);
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
