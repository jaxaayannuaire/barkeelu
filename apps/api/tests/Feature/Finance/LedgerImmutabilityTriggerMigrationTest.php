<?php

namespace Tests\Feature\Finance;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Services\Finance\LedgerPostingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerImmutabilityTriggerMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::getDriverName());

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_posted_mutation() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_TABLE_NAME = 'ledger_entries'
        AND EXISTS (
            SELECT 1
            FROM ledger_transactions
            WHERE id = COALESCE(OLD.ledger_transaction_id, NEW.ledger_transaction_id)
              AND status = 'POSTED'
        ) THEN
        RAISE EXCEPTION 'Posted ledger entry is immutable';
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;
SQL);

        $migrationPath = database_path('migrations/2026_09_22_000000_fix_ledger_immutability_trigger_function.php');
        $this->assertFileExists($migrationPath);
        $migration = require $migrationPath;
        $migration->up();
    }

    public function test_balanced_payment_ledger_posts_after_replacing_stale_trigger_function(): void
    {
        $accounts = $this->paymentAccounts();

        $transaction = app(LedgerPostingService::class)->post('payment:trigger-fix', 'XOF', [
            ['account_id' => $accounts['PAYMENT_CLEARING']->id, 'direction' => 'DEBIT', 'amount' => 105],
            ['account_id' => $accounts['CAMPAIGN_PAYABLE']->id, 'direction' => 'CREDIT', 'amount' => 100],
            ['account_id' => $accounts['PLATFORM_FEE_REVENUE']->id, 'direction' => 'CREDIT', 'amount' => 4],
            ['account_id' => $accounts['PAYOUT_PROVISION_RESERVE']->id, 'direction' => 'CREDIT', 'amount' => 1],
        ], 'Payment ledger');

        $this->assertSame('POSTED', $transaction->status->value);
        $this->assertSame(4, $transaction->entries()->count());
    }

    public function test_posted_transaction_update_and_delete_are_refused(): void
    {
        $transaction = $this->postedTransaction();

        $this->assertPostedTransactionMutationRefused(fn () => DB::table('ledger_transactions')->where('id', $transaction->id)->update(['description' => 'Mutation interdite']));
        $this->assertPostedTransactionMutationRefused(fn () => DB::table('ledger_transactions')->where('id', $transaction->id)->delete());
    }

    public function test_posted_entry_insert_update_and_delete_are_refused(): void
    {
        $transaction = $this->postedTransaction();
        $entry = $transaction->entries()->firstOrFail();
        $account = LedgerAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'code' => 'EXTRA_'.Str::upper(Str::random(8)),
            'name' => 'Extra',
            'account_type' => 'ASSET',
            'currency' => 'XOF',
        ]);

        $this->assertPostedEntryMutationRefused(fn () => LedgerEntry::query()->create([
            'ledger_transaction_id' => $transaction->id,
            'ledger_account_id' => $account->id,
            'direction' => 'DEBIT',
            'amount' => 1,
        ]));
        $this->assertPostedEntryMutationRefused(fn () => DB::table('ledger_entries')->where('id', $entry->id)->update(['amount' => 11]));
        $this->assertPostedEntryMutationRefused(fn () => DB::table('ledger_entries')->where('id', $entry->id)->delete());
    }

    private function postedTransaction()
    {
        $accounts = $this->paymentAccounts();

        return app(LedgerPostingService::class)->post('ledger:immutable:'.Str::uuid(), 'XOF', [
            ['account_id' => $accounts['PAYMENT_CLEARING']->id, 'direction' => 'DEBIT', 'amount' => 10],
            ['account_id' => $accounts['CAMPAIGN_PAYABLE']->id, 'direction' => 'CREDIT', 'amount' => 10],
        ]);
    }

    private function paymentAccounts(): array
    {
        $accounts = [];
        foreach ([
            'PAYMENT_CLEARING' => 'ASSET',
            'CAMPAIGN_PAYABLE' => 'LIABILITY',
            'PLATFORM_FEE_REVENUE' => 'REVENUE',
            'PAYOUT_PROVISION_RESERVE' => 'LIABILITY',
        ] as $code => $type) {
            $accounts[$code] = LedgerAccount::query()->create([
                'public_id' => (string) Str::uuid(),
                'code' => $code.'_'.Str::upper(Str::random(8)),
                'name' => $code,
                'account_type' => $type,
                'currency' => 'XOF',
            ]);
        }

        return $accounts;
    }

    private function assertPostedTransactionMutationRefused(callable $mutation): void
    {
        try {
            DB::transaction($mutation);
            $this->fail('Mutation transaction POSTED doit être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Posted ledger transaction is immutable', $exception->getMessage());
        }
    }

    private function assertPostedEntryMutationRefused(callable $mutation): void
    {
        try {
            DB::transaction($mutation);
            $this->fail('Mutation entry POSTED doit être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Posted ledger entry is immutable', $exception->getMessage());
        }
    }
}
