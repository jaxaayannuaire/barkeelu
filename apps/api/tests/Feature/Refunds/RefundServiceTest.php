<?php

namespace Tests\Feature\Refunds;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\Refunds\RefundService;
use Database\Seeders\FinancialFoundationSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesConfirmedDonations;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use CreatesConfirmedDonations;
    use RefreshDatabase;

    public function test_partial_multiple_refunds_are_reserved_and_executed_without_over_refund(): void
    {
        [$payment, $user] = $this->paidPayment();
        $service = app(RefundService::class);
        $one = $service->request($payment, $user, ['amount' => 40, 'currency' => 'XOF', 'idempotency_key' => 'refund-one']);
        $two = $service->request($payment, $user, ['amount' => 30, 'currency' => 'XOF', 'idempotency_key' => 'refund-two']);
        $this->assertSame(70, $payment->refresh()->reserved_refund_amount);
        $service->markProviderState($one, 'SUCCEEDED');
        $this->assertSame(30, $payment->refresh()->reserved_refund_amount);
        $this->assertSame(40, $payment->executed_refund_amount);
        $this->assertSame(RefundStatus::SUCCEEDED, $one->refresh()->status);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'REFUND_PAYABLE')->value('id'), 'amount' => 40, 'direction' => 'DEBIT']);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'PROVIDER_FUNDS')->value('id'), 'amount' => 40, 'direction' => 'CREDIT']);
        $this->expectException(DomainException::class);
        $service->request($payment, $user, ['amount' => 35, 'currency' => 'XOF', 'idempotency_key' => 'refund-over']);
    }

    public function test_refund_idempotence_timeout_and_ledger_origin_are_controlled(): void
    {
        [$payment, $user] = $this->paidPayment();
        $service = app(RefundService::class);
        $input = ['amount' => 20, 'currency' => 'XOF', 'idempotency_key' => 'refund-idempotent'];
        $refund = $service->request($payment, $user, $input);
        $this->assertSame($refund->id, $service->request($payment, $user, $input)->id);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'CAMPAIGN_PAYABLE')->value('id'), 'amount' => 20, 'direction' => 'DEBIT']);
        $service->markProviderState($refund, 'TIMEOUT');
        $this->assertSame(RefundStatus::UNKNOWN, $refund->refresh()->status);
        $this->expectException(DomainException::class);
        $service->request($payment, $user, ['amount' => 21, 'currency' => 'XOF', 'idempotency_key' => 'refund-idempotent']);
    }

    public function test_refund_of_unapplied_second_success_debits_unapplied_funds_only(): void
    {
        [$payment, $user] = $this->paidPayment();
        $second = app(PaymentService::class)->create($payment->donation, $payment->providerAccount, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-second']);
        app(PaymentService::class)->applyProviderState($second, ['provider_account_id' => $second->provider_account_id, 'internal_reference' => $second->internal_reference, 'amount' => 104, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'provider-second']);
        $refund = app(RefundService::class)->request($second, $user, ['amount' => 50, 'currency' => 'XOF', 'idempotency_key' => 'refund-unapplied']);
        app(RefundService::class)->markProviderState($refund, 'SUCCEEDED');
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'UNAPPLIED_FUNDS')->value('id'), 'amount' => 50, 'direction' => 'DEBIT']);
        $this->assertDatabaseMissing('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'CAMPAIGN_PAYABLE')->value('id'), 'amount' => 50, 'direction' => 'DEBIT']);
        $this->assertDatabaseHas('ledger_entries', ['ledger_account_id' => LedgerAccount::query()->where('code', 'PROVIDER_FUNDS')->value('id'), 'amount' => 50, 'direction' => 'CREDIT']);
    }

    #[DataProvider('nonPaidStatuses')]
    public function test_non_paid_payment_is_rejected_without_refund_or_financial_effect(PaymentStatus $status): void
    {
        [$payment, $user] = $this->paidPayment();
        $payment->update(['status' => $status]);
        $ledgerTransactions = LedgerTransaction::query()->count();
        $outboxEvents = DB::table('outbox_events')->count();

        try {
            app(RefundService::class)->request($payment, $user, ['amount' => 20, 'currency' => 'XOF', 'idempotency_key' => 'refund-'.$status->value]);
            $this->fail('Le refund non PAID doit être refusé.');
        } catch (DomainException $exception) {
            $this->assertSame('Un refund exige un Payment PAID.', $exception->getMessage());
        }

        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame(0, $payment->refresh()->reserved_refund_amount);
        $this->assertDatabaseCount('ledger_transactions', $ledgerTransactions);
        $this->assertDatabaseCount('outbox_events', $outboxEvents);
    }

    public static function nonPaidStatuses(): array
    {
        return [
            'created' => [PaymentStatus::CREATED],
            'pending' => [PaymentStatus::PENDING],
            'processing' => [PaymentStatus::PROCESSING],
            'unknown' => [PaymentStatus::UNKNOWN],
            'failed' => [PaymentStatus::FAILED],
            'cancelled' => [PaymentStatus::CANCELLED],
            'expired' => [PaymentStatus::EXPIRED],
        ];
    }

    private function paidPayment(): array
    {
        $this->seed(FinancialFoundationSeeder::class);
        $user = User::factory()->create();
        $beneficiary = Beneficiary::query()->create(['public_id' => (string) Str::uuid(), 'type' => BeneficiaryType::INDIVIDUAL, 'display_name' => 'B', 'status' => BeneficiaryStatus::ACTIVE, 'created_by_user_id' => $user->id]);
        $campaign = Campaign::query()->create(['public_id' => (string) Str::uuid(), 'owner_user_id' => $user->id, 'created_by_user_id' => $user->id, 'beneficiary_id' => $beneficiary->id, 'title' => 'C', 'slug' => 'c-'.Str::lower(Str::random(8)), 'description' => 'D', 'goal_amount' => 1000, 'currency' => 'XOF', 'status' => CampaignStatus::PUBLISHED, 'fundraising_status' => CampaignFundraisingStatus::OPEN, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE, 'visibility' => CampaignVisibility::PUBLIC]);
        $account = ProviderAccount::query()->create(['public_id' => (string) Str::uuid(), 'provider' => 'TEST', 'name' => 'Test', 'environment' => 'TEST', 'currency' => 'XOF', 'is_active' => true]);
        $donation = $this->createPendingConfirmedDonation($campaign, $user, 'donation-'.Str::uuid());
        $payment = app(PaymentService::class)->create($donation, $account, ['amount' => 104, 'currency' => 'XOF', 'idempotency_key' => 'payment-'.Str::uuid()]);
        $this->assertPendingPaymentHasNoFinancialEffects($payment);
        app(PaymentService::class)->applyProviderState($payment, ['provider_account_id' => $account->id, 'internal_reference' => $payment->internal_reference, 'amount' => 104, 'currency' => 'XOF', 'provider_status' => 'PAID', 'provider_payment_id' => 'provider-'.$payment->id]);

        return [$payment->refresh(), $user];
    }
}
