<?php

namespace Tests\Feature\Donations;

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

class DonationFactoryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_postgresql_processes_create_one_donation_for_same_confirmed_content(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('barkeelu_test', DB::scalar('select current_database()'));
        DB::connection()->commit();
        [$campaign, $user, $beneficiary] = $this->context();
        $key = 'factory-concurrency:'.Str::uuid();
        $hash = hash('sha256', 'factory-concurrency');
        DB::connection()->beginTransaction();
        $workers = [$this->startWorker($campaign->id, $key, $hash), $this->startWorker($campaign->id, $key, $hash)];

        try {
            foreach ($workers as $worker) {
                $this->assertSame(0, $this->finishWorker($worker));
            }

            $this->assertSame(1, DB::table('donations')->where('idempotency_key', $key)->count());
        } finally {
            DB::table('donations')->where('idempotency_key', $key)->delete();
            DB::table('campaigns')->where('id', $campaign->id)->delete();
            DB::table('beneficiaries')->where('id', $beneficiary->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
            DB::connection()->commit();
            DB::connection()->beginTransaction();
        }
    }

    private function startWorker(int $campaignId, string $key, string $hash): array
    {
        $command = sprintf('"%s" "%s" %d "%s" "%s"', PHP_BINARY, base_path('tests/Fixtures/Donations/concurrent_donation_factory.php'), $campaignId, $key, $hash);
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

    private function context(): array
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
            'title' => 'Campagne concurrence factory', 'slug' => 'factory-concurrency-'.Str::lower(Str::random(8)),
            'description' => 'Description', 'goal_amount' => 100_000, 'currency' => 'XOF',
            'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC,
        ]);

        return [$campaign, $user, $beneficiary];
    }
}
