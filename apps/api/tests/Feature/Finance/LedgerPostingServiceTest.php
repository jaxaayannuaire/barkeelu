<?php

namespace Tests\Feature\Finance;

use App\Models\LedgerAccount;
use App\Services\Finance\LedgerPostingService;
use App\Services\Finance\LedgerReversalService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_business_key_and_content_is_idempotent(): void
    {
        $entries = $this->entries();
        $service = app(LedgerPostingService::class);
        $first = $service->post('test:posting', 'XOF', $entries, 'Test');
        $second = $service->post('test:posting', 'XOF', $entries, 'Test');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('ledger_transactions', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_same_business_key_with_different_content_conflicts(): void
    {
        $service = app(LedgerPostingService::class);
        $service->post('test:conflict', 'XOF', $this->entries(), 'Test');
        $this->expectException(DomainException::class);
        $service->post('test:conflict', 'XOF', $this->entries(11), 'Test');
    }

    public function test_posted_transaction_is_reversed_without_mutating_original(): void
    {
        $entries = $this->entries();
        $original = app(LedgerPostingService::class)->post('test:original', 'XOF', $entries, 'Original');
        $reversal = app(LedgerReversalService::class)->reverse($original, 'test:reversal');
        $this->assertSame($original->id, $reversal->reversal_of_transaction_id);
        $this->assertSame('POSTED', $original->refresh()->status->value);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'ledger.transaction.reversed', 'aggregate_reference' => $reversal->public_id]);
        $this->expectException(DomainException::class);
        app(LedgerReversalService::class)->reverse($original, 'test:reversal-2');
    }

    public function test_invalid_posting_rolls_back_ledger_and_outbox(): void
    {
        $entries = $this->entries();
        $entries[1]['amount'] = 9;
        try {
            app(LedgerPostingService::class)->post('test:invalid', 'XOF', $entries, 'Invalid');
            $this->fail('Le posting invalide aurait dû échouer.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ledger_transactions', 0);
            $this->assertDatabaseCount('outbox_events', 0);
        }
    }

    public function test_outbox_insert_failure_rolls_back_the_complete_ledger_posting(): void
    {
        DB::unprepared(<<<'SQL'
CREATE FUNCTION test_reject_outbox_insert() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'Test controlled outbox failure';
END;
$$;
CREATE TRIGGER test_reject_outbox_insert
    BEFORE INSERT ON outbox_events
    FOR EACH ROW EXECUTE FUNCTION test_reject_outbox_insert();
SQL);

        try {
            app(LedgerPostingService::class)->post('test:outbox-failure', 'XOF', $this->entries(), 'Outbox failure');
            $this->fail('La création Outbox forcée en erreur aurait dû annuler le posting.');
        } catch (QueryException) {
            $this->assertDatabaseCount('ledger_transactions', 0);
            $this->assertDatabaseCount('ledger_entries', 0);
            $this->assertDatabaseCount('outbox_events', 0);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS test_reject_outbox_insert ON outbox_events;');
            DB::unprepared('DROP FUNCTION IF EXISTS test_reject_outbox_insert();');
        }
    }

    private function entries(int $amount = 10): array
    {
        $debit = LedgerAccount::query()->create(['public_id' => (string) Str::uuid(), 'code' => 'D'.Str::random(8), 'name' => 'Debit', 'account_type' => 'ASSET', 'currency' => 'XOF']);
        $credit = LedgerAccount::query()->create(['public_id' => (string) Str::uuid(), 'code' => 'C'.Str::random(8), 'name' => 'Credit', 'account_type' => 'LIABILITY', 'currency' => 'XOF']);

        return [['account_id' => $debit->id, 'direction' => 'DEBIT', 'amount' => $amount], ['account_id' => $credit->id, 'direction' => 'CREDIT', 'amount' => $amount]];
    }
}
