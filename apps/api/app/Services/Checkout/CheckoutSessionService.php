<?php

namespace App\Services\Checkout;

use App\Enums\CampaignFundraisingStatus;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutSessionService
{
    public function create(Campaign $campaign, ?User $user, array $input): CheckoutSession
    {
        $this->assertCampaignAdmissible($campaign);

        $payload = [
            'campaign_id' => $campaign->id,
            'donor_user_id' => $user?->id,
            'nominal_amount' => (int) $input['nominal_amount'],
            'currency' => $input['currency'],
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($campaign, $user, $input, $hash): CheckoutSession {
                $existing = CheckoutSession::query()
                    ->where('idempotency_key', $input['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    $this->assertSameContent($existing, $hash);

                    return $existing;
                }

                return CheckoutSession::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'campaign_id' => $campaign->id,
                    'donor_user_id' => $user?->id,
                    'status' => 'DRAFT',
                    'currency' => $input['currency'],
                    'nominal_amount' => (int) $input['nominal_amount'],
                    'total_payable_amount' => null,
                    'fee_snapshot' => null,
                    'donor_snapshot' => null,
                    'quote_expires_at' => null,
                    'checkout_expires_at' => now()->addHour(),
                    'confirmed_at' => null,
                    'idempotency_key' => $input['idempotency_key'],
                    'content_hash' => $hash,
                    'last_payment_id' => null,
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23505') {
                throw $exception;
            }

            $existing = CheckoutSession::query()->where('idempotency_key', $input['idempotency_key'])->first();
            if ($existing === null) {
                throw $exception;
            }
            $this->assertSameContent($existing, $hash);

            return $existing;
        }
    }

    private function assertCampaignAdmissible(Campaign $campaign): void
    {
        $admissible = Campaign::query()
            ->publiclyViewable()
            ->whereKey($campaign->id)
            ->where('fundraising_status', CampaignFundraisingStatus::OPEN->value)
            ->exists();

        if (! $admissible) {
            throw new DomainException('Campagne non admissible pour un checkout.');
        }
    }

    private function assertSameContent(CheckoutSession $existing, string $hash): void
    {
        if (! hash_equals($existing->content_hash, $hash)) {
            throw new DomainException('Conflit d’idempotence checkout.');
        }
    }
}
