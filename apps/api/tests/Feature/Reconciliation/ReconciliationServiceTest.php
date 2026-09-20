<?php

namespace Tests\Feature\Reconciliation;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\ReconciliationResult;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\LedgerTransaction;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Reconciliation\ReconciliationService;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class ReconciliationServiceTest extends TestCase
{
    use CreatesConfirmedDonations;
    use RefreshDatabase;

    public function test_all_six_results_are_classified_without_rewriting_financial_history(): void
    {
        [$service, $run] = $this->createRunContext();

        $this->assertSame(ReconciliationResult::MATCHED, $service->recordItem($run, $this->item())->result);
        $this->assertSame(ReconciliationResult::MISSING_INTERNAL, $service->recordItem($run, $this->item(['internal_reference' => null]))->result);
        $this->assertSame(ReconciliationResult::MISSING_PROVIDER, $service->recordItem($run, $this->item(['provider_reference' => null]))->result);
        $this->assertSame(ReconciliationResult::AMOUNT_MISMATCH, $service->recordItem($run, $this->item(['provider_amount' => 99]))->result);
        $this->assertSame(ReconciliationResult::STATUS_MISMATCH, $service->recordItem($run, $this->item(['provider_status' => 'FAILED']))->result);
        $this->assertSame(ReconciliationResult::REVIEW_REQUIRED, $service->recordItem($run, $this->item(['requires_review' => true]))->result);
    }

    public function test_resolution_is_audited_and_financial_correction_uses_a_ledger_reversal(): void
    {
        [$service, $run, $operator, $approver, $original] = $this->createRunContext(true);
        $item = $service->recordItem($run, $this->item(['provider_amount' => 99]));

        $resolved = $service->resolve($item, $approver, 'Montant fournisseur rapproché par reversal.', $original);

        $this->assertSame('RESOLVED_WITH_REVERSAL', $resolved->resolution_status);
        $this->assertSame($approver->id, $resolved->resolved_by_user_id);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('Montant fournisseur rapproché par reversal.', $resolved->resolution_note);
        $this->assertArrayHasKey('ledger_reversal_public_id', $resolved->metadata);
        $this->assertDatabaseHas('ledger_transactions', ['reversal_of_transaction_id' => $original->id]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'ledger.transaction.reversed']);

        $this->expectException(\DomainException::class);
        $service->resolve($resolved, $approver, 'Seconde résolution interdite.');
    }

    private function createRunContext(bool $withLedger = false): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $operator = User::factory()->create();
        $approver = User::factory()->create();
        Permission::findOrCreate('finance.operate', 'web');
        Permission::findOrCreate('finance.approve', 'web');
        $operator->givePermissionTo('finance.operate');
        $approver->givePermissionTo('finance.approve');
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $service = app(ReconciliationService::class);
        $run = $service->createRun($account, $operator, ['period_start' => now()->subDay(), 'period_end' => now(), 'source' => 'provider_export']);

        if (! $withLedger) {
            return [$service, $run];
        }

        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $operator->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $operator->id, 'created_by_user_id' => $operator->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'c-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 1_000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $donation = $this->createPendingConfirmedDonation($campaign, $operator, 'donation-'.Str::uuid());
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
        app(PaymentService::class)->applyProviderState($payment, ['provider_account_id' => $account->id, 'internal_reference' => $payment->internal_reference, 'amount' => 104, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'provider-payment']);
        $original = LedgerTransaction::query()->where('business_key', 'payment:'.$payment->public_id.':captured')->firstOrFail();

        return [$service, $run, $operator, $approver, $original];
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            'item_type' => 'PAYMENT',
            'internal_reference' => 'internal-1',
            'provider_reference' => 'provider-1',
            'internal_amount' => 100,
            'provider_amount' => 100,
            'currency' => 'XOF',
            'internal_status' => 'PAID',
            'provider_status' => 'PAID',
        ], $overrides);
    }
}
