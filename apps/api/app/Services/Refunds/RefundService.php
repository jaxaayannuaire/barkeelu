<?php

namespace App\Services\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\LedgerPostingService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RefundService
{
    public function __construct(private LedgerPostingService $ledger) {}

    public function request(Payment $payment, User $user, array $input): Refund
    {
        $hash = hash('sha256', json_encode(['payment_id' => $payment->id, 'amount' => $input['amount'], 'currency' => $input['currency']], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($payment, $user, $input, $hash): Refund {
            $existing = Refund::query()->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw new DomainException('Conflit d’idempotence refund.');
                }

                return $existing;
            }
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::PAID) {
                throw new DomainException('Un refund exige un Payment PAID.');
            }
            if ($payment->currency !== $input['currency'] || $input['amount'] > $payment->amount - $payment->reserved_refund_amount - $payment->executed_refund_amount) {
                throw new DomainException('Montant de refund non disponible.');
            }
            $refund = Refund::query()->create(['public_id' => (string) Str::uuid(), 'payment_id' => $payment->id, 'provider_account_id' => $payment->provider_account_id, 'amount' => $input['amount'], 'currency' => $input['currency'], 'status' => RefundStatus::REQUESTED, 'idempotency_key' => $input['idempotency_key'], 'content_hash' => $hash, 'requested_by_user_id' => $user->id, 'requested_at' => now()]);
            $payment->increment('reserved_refund_amount', $refund->amount);
            $this->reserveLedger($refund, $payment);

            return $refund;
        });
    }

    public function markProviderState(Refund $refund, string $state): Refund
    {
        return DB::transaction(function () use ($refund, $state): Refund {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($state === 'TIMEOUT') {
                $refund->update(['status' => RefundStatus::UNKNOWN]);

                return $refund->refresh();
            }
            if ($state !== 'SUCCEEDED') {
                $refund->update(['status' => RefundStatus::FAILED, 'last_error' => 'Échec provider refund.']);

                return $refund->refresh();
            }
            $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            $this->executeLedger($refund, $payment);
            $payment->decrement('reserved_refund_amount', $refund->amount);
            $payment->increment('executed_refund_amount', $refund->amount);
            $refund->update(['status' => RefundStatus::SUCCEEDED, 'processed_at' => now()]);

            return $refund->refresh();
        });
    }

    private function reserveLedger(Refund $refund, Payment $payment): void
    {
        $origin = LedgerTransaction::query()->where('business_key', 'payment:'.$payment->public_id.':unapplied')->exists() ? 'UNAPPLIED_FUNDS' : 'CAMPAIGN_PAYABLE';
        $accounts = LedgerAccount::query()->whereIn('code', [$origin, 'REFUND_PAYABLE'])->get()->keyBy('code');
        $this->ledger->post('refund:'.$refund->public_id.':reserved', $refund->currency, [['account_id' => $accounts[$origin]->id, 'direction' => 'DEBIT', 'amount' => $refund->amount], ['account_id' => $accounts['REFUND_PAYABLE']->id, 'direction' => 'CREDIT', 'amount' => $refund->amount]], 'Refund reserved');
    }

    private function executeLedger(Refund $refund, Payment $payment): void
    {
        $accounts = LedgerAccount::query()->whereIn('code', ['REFUND_PAYABLE', 'PROVIDER_FUNDS'])->get()->keyBy('code');
        $this->ledger->post('refund:'.$refund->public_id.':executed', $refund->currency, [['account_id' => $accounts['REFUND_PAYABLE']->id, 'direction' => 'DEBIT', 'amount' => $refund->amount], ['account_id' => $accounts['PROVIDER_FUNDS']->id, 'direction' => 'CREDIT', 'amount' => $refund->amount]], 'Refund executed');
    }
}
