<?php

namespace App\Services\Reconciliation;

use App\Enums\ReconciliationResult;
use App\Models\LedgerTransaction;
use App\Models\ProviderAccount;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\Finance\LedgerReversalService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconciliationService
{
    public function __construct(private LedgerReversalService $reversals) {}

    public function createRun(ProviderAccount $account, User $user, array $input): ReconciliationRun
    {
        if (! $user->can('finance.operate')) {
            throw new DomainException('Permission finance operator requise.');
        }

        return ReconciliationRun::query()->create([
            'public_id' => (string) Str::uuid(),
            'provider_account_id' => $account->id,
            'period_start' => $input['period_start'],
            'period_end' => $input['period_end'],
            'source' => $input['source'],
            'status' => 'COMPLETED',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function recordItem(ReconciliationRun $run, array $input): ReconciliationItem
    {
        $result = $this->classify($input);

        return ReconciliationItem::query()->create([
            'public_id' => (string) Str::uuid(),
            'reconciliation_run_id' => $run->id,
            'item_type' => $input['item_type'],
            'internal_reference' => $input['internal_reference'] ?? null,
            'provider_reference' => $input['provider_reference'] ?? null,
            'internal_amount' => $input['internal_amount'] ?? null,
            'provider_amount' => $input['provider_amount'] ?? null,
            'currency' => $input['currency'],
            'internal_status' => $input['internal_status'] ?? null,
            'provider_status' => $input['provider_status'] ?? null,
            'result' => $result,
            'metadata' => $input['metadata'] ?? null,
        ]);
    }

    public function resolve(ReconciliationItem $item, User $user, string $note, ?LedgerTransaction $correction = null): ReconciliationItem
    {
        if (! $user->can('finance.approve')) {
            throw new DomainException('Permission finance approver requise.');
        }

        return DB::transaction(function () use ($item, $user, $note, $correction): ReconciliationItem {
            $item = ReconciliationItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($item->resolved_at !== null) {
                throw new DomainException('Élément de reconciliation déjà résolu.');
            }

            $metadata = $item->metadata ?? [];
            $resolutionStatus = 'RESOLVED';

            if ($correction !== null) {
                $reversal = $this->reversals->reverse($correction, 'reconciliation:'.$item->public_id.':reversal');
                $metadata['ledger_reversal_public_id'] = $reversal->public_id;
                $resolutionStatus = 'RESOLVED_WITH_REVERSAL';
            }

            $item->update([
                'resolution_status' => $resolutionStatus,
                'resolved_by_user_id' => $user->id,
                'resolved_at' => now(),
                'resolution_note' => $note,
                'metadata' => $metadata,
            ]);

            return $item->refresh();
        });
    }

    private function classify(array $input): ReconciliationResult
    {
        if (empty($input['internal_reference'])) {
            return ReconciliationResult::MISSING_INTERNAL;
        }

        if (empty($input['provider_reference'])) {
            return ReconciliationResult::MISSING_PROVIDER;
        }

        if (($input['internal_amount'] ?? null) !== ($input['provider_amount'] ?? null)) {
            return ReconciliationResult::AMOUNT_MISMATCH;
        }

        if (($input['internal_status'] ?? null) !== ($input['provider_status'] ?? null)) {
            return ReconciliationResult::STATUS_MISMATCH;
        }

        if (($input['requires_review'] ?? false) === true) {
            return ReconciliationResult::REVIEW_REQUIRED;
        }

        return ReconciliationResult::MATCHED;
    }
}
