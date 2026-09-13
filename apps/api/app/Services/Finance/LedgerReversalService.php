<?php

namespace App\Services\Finance;

use App\Enums\LedgerEntryDirection;
use App\Enums\LedgerTransactionStatus;
use App\Models\LedgerTransaction;
use DomainException;

class LedgerReversalService
{
    public function __construct(private LedgerPostingService $postingService) {}

    public function reverse(LedgerTransaction $original, string $businessKey): LedgerTransaction
    {
        if ($original->status !== LedgerTransactionStatus::POSTED) {
            throw new DomainException('Seule une transaction POSTED peut être reversée.');
        }

        if (LedgerTransaction::query()->where('reversal_of_transaction_id', $original->id)->exists()) {
            throw new DomainException('Un reversal existe déjà pour cette transaction.');
        }

        $entries = $original->entries()->get()->map(static fn ($entry): array => [
            'account_id' => $entry->ledger_account_id,
            'campaign_id' => $entry->campaign_id,
            'direction' => $entry->direction === LedgerEntryDirection::DEBIT
                ? LedgerEntryDirection::CREDIT->value
                : LedgerEntryDirection::DEBIT->value,
            'amount' => $entry->amount,
        ])->all();

        return $this->postingService->post(
            $businessKey,
            $original->currency,
            $entries,
            'Reversal '.$original->business_key,
            $original->id,
            'ledger.transaction.reversed',
        );
    }
}
