<?php

namespace Tests\Unit\Support;

use App\Support\FeeLabel;
use PHPUnit\Framework\TestCase;

class FeeLabelTest extends TestCase
{
    public function test_it_returns_safe_user_labels_for_snapshot_fee_types(): void
    {
        $this->assertSame('Frais de plateforme', FeeLabel::for('PLATFORM_FEE'));
        $this->assertSame('Provision de transfert', FeeLabel::for('PAYOUT_PROVISION'));
        $this->assertSame('Frais applicables', FeeLabel::for('UNEXPECTED_FEE'));
        $this->assertSame('Frais applicables', FeeLabel::for(null));
    }
}
