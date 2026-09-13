<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\FeeCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class FeeCalculatorTest extends TestCase
{
    public function test_integer_basis_points_round_half_up(): void
    {
        $calculator = app(FeeCalculator::class);

        $this->assertSame(400, $calculator->calculate(10000, 400));
        $this->assertSame(100, $calculator->calculate(10000, 100));
        $this->assertSame(0, $calculator->calculate(1, 4999));
        $this->assertSame(1, $calculator->calculate(1, 5000));
    }

    public function test_negative_inputs_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(FeeCalculator::class)->calculate(-1, 100);
    }
}
