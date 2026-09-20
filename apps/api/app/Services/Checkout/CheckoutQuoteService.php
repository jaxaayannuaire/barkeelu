<?php

namespace App\Services\Checkout;

use App\Enums\CheckoutStatus;
use App\Enums\FeeType;
use App\Models\CheckoutSession;
use App\Models\FeePolicy;
use App\Services\Finance\FeeCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;

class CheckoutQuoteService
{
    public function quote(CheckoutSession $session, array $input): CheckoutSession
    {
        return DB::transaction(function () use ($session, $input): CheckoutSession {
            $locked = CheckoutSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! in_array($locked->status, [CheckoutStatus::DRAFT, CheckoutStatus::QUOTED], true)) {
                throw new DomainException('Cette session ne peut plus être quotée.');
            }

            $now = now();
            if ($locked->checkout_expires_at->isPast()) {
                throw new DomainException('Checkout expiré.');
            }

            $donorSnapshot = [
                'name' => $input['name'] ?? null,
                'email' => $input['email'] ?? null,
                'is_anonymous' => (bool) ($input['is_anonymous'] ?? false),
                'show_name' => (bool) ($input['show_name'] ?? false),
                'show_amount' => (bool) ($input['show_amount'] ?? false),
            ];

            $fees = FeePolicy::query()
                ->where('active', true)
                ->where('effective_from', '<=', $now)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $now))
                ->where(function ($query) use ($locked): void {
                    $query->whereNull('currency')->orWhere('currency', $locked->currency);
                })
                ->whereIn('fee_type', [FeeType::PLATFORM_FEE->value, FeeType::PAYOUT_PROVISION->value])
                ->orderBy('id')
                ->get()
                ->map(function (FeePolicy $policy) use ($locked): array {
                    $basis = $locked->nominal_amount;
                    $amount = app(FeeCalculator::class)->calculate(
                        $basis,
                        (int) ($policy->rate_bps ?? 0),
                        (int) ($policy->fixed_amount ?? 0),
                    );

                    return [
                        'policy_id' => $policy->id,
                        'policy_version' => $policy->updated_at?->toIso8601String() ?? $policy->id,
                        'fee_type' => $policy->fee_type->value,
                        'calculation_parameters' => [
                            'rate_bps' => $policy->rate_bps,
                            'fixed_amount' => $policy->fixed_amount,
                            'currency' => $policy->currency,
                        ],
                        'basis_points' => $policy->rate_bps,
                        'fixed_amount' => $policy->fixed_amount,
                        'calculation_base_amount' => $basis,
                        'rounding_rule' => 'HALF_UP_INTEGER',
                        'calculated_amount' => $amount,
                        'currency' => $locked->currency,
                    ];
                })->values()->all();

            $total = $locked->nominal_amount + array_sum(array_column($fees, 'calculated_amount'));
            $quoteExpiresAt = $now->copy()->addMinutes(15);
            if ($quoteExpiresAt->greaterThan($locked->checkout_expires_at)) {
                $quoteExpiresAt = $locked->checkout_expires_at->copy();
            }

            $existingHistory = is_array($locked->fee_snapshot)
                ? array_map(fn (array $entry): array => $this->flatHistoryEntry($entry), $locked->fee_snapshot['quote_history'] ?? [])
                : [];
            $previousSnapshot = is_array($locked->fee_snapshot) && $locked->quote_expires_at?->isPast()
                ? [$this->flatHistoryEntry([
                    'fee_snapshot' => $locked->fee_snapshot,
                    'donor_snapshot' => $locked->donor_snapshot,
                    'quote_expires_at' => $locked->quote_expires_at,
                ]), ...$existingHistory]
                : $existingHistory;

            $locked->update([
                'status' => CheckoutStatus::QUOTED,
                'total_payable_amount' => $total,
                'fee_snapshot' => [
                    'fees' => $fees,
                    'currency' => $locked->currency,
                    'nominal_amount' => $locked->nominal_amount,
                    'total_payable_amount' => $total,
                    'quote_history' => $previousSnapshot,
                ],
                'donor_snapshot' => $donorSnapshot,
                'payer_mobile_encrypted' => $input['phone'] ?? $locked->payer_mobile_encrypted,
                'quote_expires_at' => $quoteExpiresAt,
            ]);

            return $locked->refresh();
        });
    }

    private function flatHistoryEntry(array $entry): array
    {
        $feeSnapshot = is_array($entry['fee_snapshot'] ?? null) ? $entry['fee_snapshot'] : [];

        return [
            'fee_snapshot' => [
                'fees' => $feeSnapshot['fees'] ?? [],
                'currency' => $feeSnapshot['currency'] ?? null,
                'nominal_amount' => $feeSnapshot['nominal_amount'] ?? null,
                'total_payable_amount' => $feeSnapshot['total_payable_amount'] ?? null,
            ],
            'quote_expires_at' => $entry['quote_expires_at'] ?? null,
        ];
    }
}
