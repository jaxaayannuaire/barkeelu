<?php

namespace Database\Seeders;

use App\Models\FeePolicy;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinancialFoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['PAYMENT_CLEARING', 'Payment clearing', 'ASSET'],
            ['SETTLEMENT_CLEARING', 'Settlement clearing', 'ASSET'],
            ['PROVIDER_FUNDS', 'Provider funds', 'ASSET'],
            ['CAMPAIGN_PAYABLE', 'Campaign payable', 'LIABILITY'],
            ['PAYOUT_PROVISION_RESERVE', 'Payout provision reserve', 'LIABILITY'],
            ['REFUND_PAYABLE', 'Refund payable', 'LIABILITY'],
            ['UNAPPLIED_FUNDS', 'Unapplied funds', 'LIABILITY'],
            ['PLATFORM_FEE_REVENUE', 'Platform fee revenue', 'REVENUE'],
            ['PAYMENT_PROVIDER_FEE_EXPENSE', 'Payment provider fee expense', 'EXPENSE'],
            ['PAYOUT_PROVIDER_FEE_EXPENSE', 'Payout provider fee expense', 'EXPENSE'],
        ] as [$code, $name, $type]) {
            LedgerAccount::query()->updateOrCreate(
                ['code' => $code],
                [
                    'public_id' => (string) Str::uuid(),
                    'name' => $name,
                    'account_type' => $type,
                    'currency' => 'XOF',
                    'active' => true,
                ],
            );
        }

        foreach ([
            ['PLATFORM_FEE', 'PLATFORM_FEE', 400, true],
            ['PAYOUT_PROVISION_WORKING', 'PAYOUT_PROVISION', 100, false],
        ] as [$code, $type, $rateBps, $active]) {
            FeePolicy::query()->updateOrCreate(
                ['code' => $code],
                [
                    'public_id' => (string) Str::uuid(),
                    'fee_type' => $type,
                    'rate_bps' => $rateBps,
                    'currency' => 'XOF',
                    'effective_from' => now(),
                    'active' => $active,
                ],
            );
        }
    }
}
