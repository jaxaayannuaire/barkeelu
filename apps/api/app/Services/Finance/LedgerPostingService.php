<?php

namespace App\Services\Finance;

use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\OutboxEvent;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerPostingService
{
    public function post(
        string $key,
        string $currency,
        array $entries,
        ?string $description = null,
        ?int $reversalOfTransactionId = null,
        string $eventType = 'ledger.transaction.posted',
    ): LedgerTransaction {
        $payload = [
            'business_key' => $key,
            'currency' => $currency,
            'description' => $description,
            'entries' => $entries,
            'reversal_of_transaction_id' => $reversalOfTransactionId,
            'event_type' => $eventType,
        ];

        usort($payload['entries'], fn (array $left, array $right): int => strcmp(json_encode($left), json_encode($right)));
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($key, $currency, $entries, $description, $hash, $reversalOfTransactionId, $eventType): LedgerTransaction {
                $existing = LedgerTransaction::query()->where('business_key', $key)->lockForUpdate()->first();

                if ($existing !== null) {
                    if (! hash_equals($existing->content_hash, $hash)) {
                        throw new DomainException('Conflit d’idempotence ledger.');
                    }

                    return $existing;
                }

                $transaction = LedgerTransaction::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'business_key' => $key,
                    'content_hash' => $hash,
                    'currency' => $currency,
                    'status' => 'DRAFT',
                    'description' => $description,
                    'reversal_of_transaction_id' => $reversalOfTransactionId,
                ]);

                foreach ($entries as $entry) {
                    LedgerEntry::query()->create([
                        'ledger_transaction_id' => $transaction->id,
                        'ledger_account_id' => $entry['account_id'],
                        'campaign_id' => $entry['campaign_id'] ?? null,
                        'direction' => $entry['direction'],
                        'amount' => $entry['amount'],
                    ]);
                }

                $transaction->update(['status' => 'POSTED', 'posted_at' => now()]);

                // Déclenche explicitement les contraintes différées avant l’Outbox.
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

                OutboxEvent::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'event_type' => $eventType,
                    'aggregate_type' => 'ledger_transaction',
                    'aggregate_reference' => $transaction->public_id,
                    'dedupe_key' => $eventType.':'.$transaction->public_id,
                    'payload' => [
                        'ledger_transaction_public_id' => $transaction->public_id,
                        'business_key' => $key,
                        'currency' => $currency,
                    ],
                    'occurred_at' => now(),
                    'available_at' => now(),
                ]);

                return $transaction->refresh();
            });
        } catch (QueryException $exception) {
            if (! $this->isBusinessKeyUniqueViolation($exception)) {
                throw $exception;
            }

            $existing = LedgerTransaction::query()->where('business_key', $key)->first();

            if ($existing !== null && hash_equals($existing->content_hash, $hash)) {
                return $existing;
            }

            throw new DomainException('Conflit d’idempotence ledger.', previous: $exception);
        }
    }

    private function isBusinessKeyUniqueViolation(QueryException $exception): bool
    {
        return ($exception->errorInfo[0] ?? null) === '23505'
            && str_contains($exception->getMessage(), 'ledger_transactions_business_key_unique');
    }
}
