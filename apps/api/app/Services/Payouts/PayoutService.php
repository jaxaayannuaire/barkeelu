<?php

namespace App\Services\Payouts;

use App\Enums\PayoutStatus;
use App\Models\Campaign;
use App\Models\LedgerAccount;
use App\Models\Payout;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Finance\LedgerPostingService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayoutService
{
    public function __construct(private LedgerPostingService $ledger) {}

    public function request(Campaign $campaign, ProviderAccount $account, User $user, array $input): Payout
    {
        if (! $user->can('finance.operate')) {
            throw new DomainException('Permission finance operator requise.');
        }

        if (! $account->is_active
            || $account->currency !== $input['currency']
            || $campaign->currency !== $input['currency']
            || $input['currency'] !== 'XOF'
            || $input['amount'] <= 0) {
            throw new DomainException('Demande de payout incompatible avec le compte fournisseur.');
        }

        $payload = [
            'campaign_id' => $campaign->id,
            'beneficiary_id' => $campaign->beneficiary_id,
            'provider_account_id' => $account->id,
            'amount' => $input['amount'],
            'currency' => $input['currency'],
            'destination_snapshot' => $input['destination_snapshot'],
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($campaign, $account, $user, $input, $hash): Payout {
            $existing = Payout::query()->where('idempotency_key', $input['idempotency_key'])->lockForUpdate()->first();

            if ($existing !== null) {
                if (! hash_equals($existing->content_hash, $hash)) {
                    throw new DomainException('Conflit d’idempotence payout.');
                }

                return $existing;
            }

            return Payout::query()->create([
                'public_id' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'beneficiary_id' => $campaign->beneficiary_id,
                'provider_account_id' => $account->id,
                'amount' => $input['amount'],
                'currency' => $input['currency'],
                'status' => PayoutStatus::PENDING_APPROVAL,
                'idempotency_key' => $input['idempotency_key'],
                'content_hash' => $hash,
                'destination_snapshot' => $input['destination_snapshot'],
                'requested_by_user_id' => $user->id,
                'requested_at' => now(),
            ]);
        });
    }

    public function approve(Payout $payout, User $user): Payout
    {
        if (! $user->can('finance.approve')) {
            throw new DomainException('Permission finance approver requise.');
        }

        return DB::transaction(function () use ($payout, $user): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($payout->campaign_id);

            if ($payout->requested_by_user_id === $user->id) {
                throw new DomainException('Un demandeur ne peut pas approuver son propre payout.');
            }

            if ($payout->status !== PayoutStatus::PENDING_APPROVAL) {
                throw new DomainException('Payout non approvable.');
            }

            if ($this->available($campaign->id) < $payout->amount) {
                throw new DomainException('Solde ledger insuffisant.');
            }

            $payout->update([
                'status' => PayoutStatus::RESERVED,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'approved_destination_snapshot' => $payout->destination_snapshot,
            ]);
            $this->post($payout, 'reserved');

            return $payout->refresh();
        });
    }

    public function providerState(Payout $payout, string $state): Payout
    {
        return DB::transaction(function () use ($payout, $state): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if (in_array($payout->status, [PayoutStatus::SUCCEEDED, PayoutStatus::CANCELLED], true)) {
                return $payout;
            }

            if ($payout->status !== PayoutStatus::RESERVED) {
                throw new DomainException('Transition fournisseur payout non autorisée.');
            }

            if ($state === 'TIMEOUT') {
                $payout->update(['status' => PayoutStatus::UNKNOWN]);

                return $payout->refresh();
            }

            if ($state === 'SUCCEEDED') {
                $this->post($payout, 'executed');
                $payout->update(['status' => PayoutStatus::SUCCEEDED, 'processed_at' => now()]);

                return $payout->refresh();
            }

            $this->post($payout, 'released');
            $payout->update(['status' => PayoutStatus::CANCELLED]);

            return $payout->refresh();
        });
    }

    /** Toute modification critique révoque l'approbation et libère la réservation. */
    public function updateCritical(Payout $payout, array $changes): Payout
    {
        $allowed = ['amount', 'currency', 'beneficiary_id', 'destination_snapshot', 'provider_account_id'];
        $changes = array_intersect_key($changes, array_flip($allowed));

        if ($changes === []) {
            throw new DomainException('Aucune donnée critique de payout à modifier.');
        }

        return DB::transaction(function () use ($payout, $changes): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if (! in_array($payout->status, [PayoutStatus::PENDING_APPROVAL, PayoutStatus::RESERVED], true)) {
                throw new DomainException('Modification critique payout non autorisée dans cet état.');
            }

            if ($payout->status === PayoutStatus::RESERVED) {
                $this->post($payout, 'released');
            }

            $payout->update(array_merge($changes, [
                'status' => PayoutStatus::PENDING_APPROVAL,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'processed_at' => null,
                'reservation_version' => $payout->reservation_version + 1,
            ]));

            return $payout->refresh();
        });
    }

    private function available(int $campaignId): int
    {
        return (int) DB::table('ledger_entries')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')
            ->where('ledger_entries.campaign_id', $campaignId)
            ->where('ledger_accounts.code', 'CAMPAIGN_PAYABLE')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'CREDIT' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');
    }

    private function post(Payout $payout, string $type): void
    {
        $codes = match ($type) {
            'reserved' => ['CAMPAIGN_PAYABLE', 'PAYOUT_RESERVED'],
            'executed' => ['PAYOUT_RESERVED', 'PROVIDER_FUNDS'],
            'released' => ['PAYOUT_RESERVED', 'CAMPAIGN_PAYABLE'],
            default => throw new DomainException('Type de posting payout inconnu.'),
        };

        $accounts = LedgerAccount::query()->whereIn('code', $codes)->get()->keyBy('code');

        $this->ledger->post(
            'payout:'.$payout->public_id.':v'.$payout->reservation_version.':'.$type,
            $payout->currency,
            [
                ['account_id' => $accounts[$codes[0]]->id, 'campaign_id' => $payout->campaign_id, 'direction' => 'DEBIT', 'amount' => $payout->amount],
                ['account_id' => $accounts[$codes[1]]->id, 'campaign_id' => $payout->campaign_id, 'direction' => 'CREDIT', 'amount' => $payout->amount],
            ],
            'Payout '.$type,
        );
    }
}
