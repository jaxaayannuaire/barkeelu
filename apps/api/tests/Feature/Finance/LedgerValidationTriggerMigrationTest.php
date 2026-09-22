<?php

namespace Tests\Feature\Finance;

use App\Models\LedgerAccount;
use App\Services\Finance\LedgerPostingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerValidationTriggerMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::getDriverName());
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION validate_posted_ledger() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    tx bigint;
BEGIN
    tx := COALESCE(
        NEW.ledger_transaction_id,
        OLD.ledger_transaction_id,
        NEW.id,
        OLD.id
    );

    RETURN NULL;
END;
$$;
SQL);

        $migrationPath = database_path('migrations/2026_09_22_010000_fix_validate_posted_ledger_function.php');
        $this->assertFileExists($migrationPath);
        $migration = require $migrationPath;
        $migration->up();
    }

    public function test_balanced_payment_ledger_posts_and_flushes_deferred_constraints(): void
    {
        $transaction = app(LedgerPostingService::class)->post('payment:validation-trigger', 'XOF', $this->paymentEntries());

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        $this->assertSame('POSTED', $transaction->status->value);
        $this->assertSame(4, $transaction->entries()->count());
    }

    public function test_unbalanced_ledger_is_refused(): void
    {
        $entries = $this->paymentEntries();
        $entries[3]['amount'] = 2;

        $this->assertInvalidPostingRefused($entries);
    }

    public function test_single_entry_ledger_is_refused(): void
    {
        $entries = $this->paymentEntries();

        $this->assertInvalidPostingRefused([array_shift($entries)]);
    }

    public function test_account_currency_mismatch_is_refused(): void
    {
        $debit = $this->account('PAYMENT_CLEARING', 'ASSET', 'XOF');
        $credit = $this->account('CAMPAIGN_PAYABLE', 'LIABILITY', 'EUR');

        $this->assertInvalidPostingRefused([
            ['account_id' => $debit->id, 'direction' => 'DEBIT', 'amount' => 105],
            ['account_id' => $credit->id, 'direction' => 'CREDIT', 'amount' => 105],
        ]);
    }

    public function test_r6_posted_transaction_immutability_remains_active(): void
    {
        $transaction = app(LedgerPostingService::class)->post('ledger:r6-immutability', 'XOF', [
            ['account_id' => $this->account('PAYMENT_CLEARING', 'ASSET')->id, 'direction' => 'DEBIT', 'amount' => 10],
            ['account_id' => $this->account('CAMPAIGN_PAYABLE', 'LIABILITY')->id, 'direction' => 'CREDIT', 'amount' => 10],
        ]);

        try {
            DB::transaction(fn () => DB::table('ledger_transactions')->where('id', $transaction->id)->update(['description' => 'Interdit']));
            $this->fail('Mutation transaction POSTED doit être refusée.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Posted ledger transaction is immutable', $exception->getMessage());
        }
    }

    private function assertInvalidPostingRefused(array $entries): void
    {
        try {
            app(LedgerPostingService::class)->post('ledger:invalid:'.Str::uuid(), 'XOF', $entries);
            $this->fail('Ledger POSTED invalide doit être refusé.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Invalid posted ledger transaction', $exception->getMessage());
        }
    }

    private function paymentEntries(): array
    {
        return [
            ['account_id' => $this->account('PAYMENT_CLEARING', 'ASSET')->id, 'direction' => 'DEBIT', 'amount' => 105],
            ['account_id' => $this->account('CAMPAIGN_PAYABLE', 'LIABILITY')->id, 'direction' => 'CREDIT', 'amount' => 100],
            ['account_id' => $this->account('PLATFORM_FEE_REVENUE', 'REVENUE')->id, 'direction' => 'CREDIT', 'amount' => 4],
            ['account_id' => $this->account('PAYOUT_PROVISION_RESERVE', 'LIABILITY')->id, 'direction' => 'CREDIT', 'amount' => 1],
        ];
    }

    private function account(string $code, string $type, string $currency = 'XOF'): LedgerAccount
    {
        return LedgerAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'code' => $code.'_'.Str::upper(Str::random(8)),
            'name' => $code,
            'account_type' => $type,
            'currency' => $currency,
        ]);
    }
}
