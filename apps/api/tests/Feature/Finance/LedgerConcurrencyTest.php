<?php

namespace Tests\Feature\Finance;

use App\Models\LedgerAccount;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LedgerConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_postgresql_connections_recover_the_same_business_key_without_duplication(): void
    {
        $entries = $this->entries();
        $businessKey = 'test:concurrent:'.Str::uuid();

        DB::unprepared(<<<'SQL'
CREATE FUNCTION test_delay_ledger_transaction_insert() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    PERFORM pg_sleep(0.5);
    RETURN NEW;
END;
$$;
CREATE TRIGGER test_delay_ledger_transaction_insert
    BEFORE INSERT ON ledger_transactions
    FOR EACH ROW EXECUTE FUNCTION test_delay_ledger_transaction_insert();
SQL);

        try {
            $first = $this->startWorker($businessKey, $entries);
            $second = $this->startWorker($businessKey, $entries);

            [$firstOutput, $firstStatus] = $this->finishWorker($first);
            [$secondOutput, $secondStatus] = $this->finishWorker($second);

            $this->assertSame(0, $firstStatus, $firstOutput);
            $this->assertSame(0, $secondStatus, $secondOutput);

            $firstResult = json_decode(trim($firstOutput), true);
            $secondResult = json_decode(trim($secondOutput), true);

            $this->assertIsArray($firstResult, $firstOutput);
            $this->assertIsArray($secondResult, $secondOutput);

            $this->assertSame('barkeelu_test', $firstResult['database']);
            $this->assertSame($firstResult['database'], $secondResult['database']);
            $this->assertSame($firstResult['id'], $secondResult['id']);
            $this->assertDatabaseCount('ledger_transactions', 1);
            $this->assertDatabaseCount('ledger_entries', 2);
            $this->assertDatabaseCount('outbox_events', 1);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS test_delay_ledger_transaction_insert ON ledger_transactions;');
            DB::unprepared('DROP FUNCTION IF EXISTS test_delay_ledger_transaction_insert();');
        }
    }

    private function entries(): array
    {
        $debit = LedgerAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'code' => 'D'.Str::random(8),
            'name' => 'Debit',
            'account_type' => 'ASSET',
            'currency' => 'XOF',
        ]);
        $credit = LedgerAccount::query()->create([
            'public_id' => (string) Str::uuid(),
            'code' => 'C'.Str::random(8),
            'name' => 'Credit',
            'account_type' => 'LIABILITY',
            'currency' => 'XOF',
        ]);

        return [
            ['account_id' => $debit->id, 'direction' => 'DEBIT', 'amount' => 10],
            ['account_id' => $credit->id, 'direction' => 'CREDIT', 'amount' => 10],
        ];
    }

    private function startWorker(string $businessKey, array $entries): array
    {
        $command = sprintf(
            '"%s" "%s" "%s" "%s"',
            PHP_BINARY,
            base_path('tests/Fixtures/Finance/concurrent_ledger_posting.php'),
            $businessKey,
            base64_encode(json_encode($entries, JSON_THROW_ON_ERROR)),
        );

        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());

        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    private function finishWorker(array $worker): array
    {
        [$process, $pipes] = $worker;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [$output.$error, proc_close($process)];
    }
}
