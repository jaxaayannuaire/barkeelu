<?php

namespace Tests\Feature\Finance;

use App\Models\FeePolicy;
use App\Models\LedgerAccount;
use Database\Seeders\FinancialFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialFoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_financial_seed_is_idempotent(): void
    {
        $expectedLedgerAccountCodes = [
            'CAMPAIGN_PAYABLE',
            'PAYMENT_CLEARING',
            'PAYMENT_PROVIDER_FEE_EXPENSE',
            'PAYOUT_PROVIDER_FEE_EXPENSE',
            'PAYOUT_PROVISION_RESERVE',
            'PAYOUT_RESERVED',
            'PLATFORM_FEE_REVENUE',
            'PROVIDER_FUNDS',
            'REFUND_PAYABLE',
            'SETTLEMENT_CLEARING',
            'UNAPPLIED_FUNDS',
        ];

        $this->seed(FinancialFoundationSeeder::class);
        $this->seed(FinancialFoundationSeeder::class);
        $this->assertSame($expectedLedgerAccountCodes, LedgerAccount::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(count($expectedLedgerAccountCodes), LedgerAccount::query()->count());
        $this->assertSame(2, FeePolicy::query()->count());
        $this->assertDatabaseHas('fee_policies', ['code' => 'PLATFORM_FEE', 'rate_bps' => 400, 'currency' => 'XOF', 'active' => true]);
        $this->assertDatabaseHas('fee_policies', ['code' => 'PAYOUT_PROVISION_WORKING', 'rate_bps' => 100, 'currency' => 'XOF', 'active' => false]);
    }
}
