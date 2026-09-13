<?php

namespace Tests\Feature\Finance;

use App\Models\AppliedFee;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerPostgresTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_rejects_unbalanced_posted_transaction_at_commit(): void
    {
        $accounts = $this->accounts();
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($accounts): void {
            $tx = $this->transaction('DRAFT');
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 10]);
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 9]);
            $tx->update(['status' => 'POSTED']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public function test_postgresql_accepts_balanced_posted_transaction(): void
    {
        $accounts = $this->accounts();
        DB::transaction(function () use ($accounts): void {
            $tx = $this->transaction('DRAFT');
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 10]);
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 10]);
            $tx->update(['status' => 'POSTED']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
        $this->assertDatabaseCount('ledger_transactions', 1);
    }

    public function test_postgresql_rejects_single_entry_and_currency_mismatch(): void
    {
        $accounts = $this->accounts();
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($accounts): void {
            $tx = $this->transaction('DRAFT');
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 10]);
            $tx->update(['status' => 'POSTED']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public function test_postgresql_rejects_non_positive_amount(): void
    {
        $this->expectException(QueryException::class);
        $account = $this->accounts()[0];
        $tx = $this->transaction('DRAFT');
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $account->id, 'direction' => 'DEBIT', 'amount' => 0]);
    }

    public function test_postgresql_rejects_account_currency_mismatch_at_deferred_check(): void
    {
        $accounts = $this->accounts();
        $accounts[1]->update(['currency' => 'EUR']);
        $this->expectException(QueryException::class);
        DB::transaction(function () use ($accounts): void {
            $tx = $this->transaction('DRAFT');
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 10]);
            LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 10]);
            $tx->update(['status' => 'POSTED']);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    public function test_posted_transaction_and_entries_are_immutable(): void
    {
        $accounts = $this->accounts();
        $tx = $this->transaction('DRAFT');
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 10]);
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 10]);
        $tx->update(['status' => 'POSTED']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->expectException(QueryException::class);
        $tx->update(['description' => 'interdit']);
    }

    public function test_applied_fee_is_immutable_after_insert(): void
    {
        $fee = AppliedFee::query()->create(['public_id' => (string) Str::uuid(), 'source_type' => 'TEST', 'source_reference' => 'test', 'fee_type' => 'PLATFORM_FEE', 'basis_amount' => 1, 'amount' => 1, 'currency' => 'XOF', 'payer' => 'payer', 'beneficiary' => 'beneficiary']);
        $this->expectException(QueryException::class);
        $fee->update(['amount' => 2]);
    }

    public function test_posted_entry_insertion_is_rejected(): void
    {
        $accounts = $this->accounts();
        $tx = $this->transaction('DRAFT');
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 1]);
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 1]);
        $tx->update(['status' => 'POSTED']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->expectException(QueryException::class);
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 1]);
    }

    public function test_posted_transaction_deletion_is_rejected(): void
    {
        $tx = $this->transaction('POSTED');
        $this->expectException(QueryException::class);
        $tx->delete();
    }

    public function test_posted_entry_update_is_rejected(): void
    {
        $accounts = $this->accounts();
        $tx = $this->transaction('DRAFT');
        $entry = LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 1]);
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 1]);
        $tx->update(['status' => 'POSTED']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->expectException(QueryException::class);
        $entry->update(['amount' => 2]);
    }

    public function test_applied_fee_deletion_is_rejected(): void
    {
        $fee = AppliedFee::query()->create(['public_id' => (string) Str::uuid(), 'source_type' => 'TEST', 'source_reference' => 'delete', 'fee_type' => 'PLATFORM_FEE', 'basis_amount' => 1, 'amount' => 1, 'currency' => 'XOF', 'payer' => 'payer', 'beneficiary' => 'beneficiary']);
        $this->expectException(QueryException::class);
        $fee->delete();
    }

    public function test_posted_entry_deletion_is_rejected(): void
    {
        $accounts = $this->accounts();
        $tx = $this->transaction('DRAFT');
        $entry = LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[0]->id, 'direction' => 'DEBIT', 'amount' => 1]);
        LedgerEntry::query()->create(['ledger_transaction_id' => $tx->id, 'ledger_account_id' => $accounts[1]->id, 'direction' => 'CREDIT', 'amount' => 1]);
        $tx->update(['status' => 'POSTED']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->expectException(QueryException::class);
        $entry->delete();
    }

    private function accounts(): array
    {
        return [LedgerAccount::query()->create(['public_id' => (string) Str::uuid(), 'code' => 'A'.Str::random(8), 'name' => 'A', 'account_type' => 'ASSET', 'currency' => 'XOF']), LedgerAccount::query()->create(['public_id' => (string) Str::uuid(), 'code' => 'B'.Str::random(8), 'name' => 'B', 'account_type' => 'LIABILITY', 'currency' => 'XOF'])];
    }

    private function transaction(string $status): LedgerTransaction
    {
        return LedgerTransaction::query()->create(['public_id' => (string) Str::uuid(), 'business_key' => Str::uuid(), 'content_hash' => str_repeat('a', 64), 'currency' => 'XOF', 'status' => $status, 'posted_at' => now()]);
    }
}
