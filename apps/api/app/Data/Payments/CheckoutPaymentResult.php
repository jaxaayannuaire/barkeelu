<?php

namespace App\Data\Payments;

use App\Models\Payment;

final readonly class CheckoutPaymentResult
{
    public function __construct(
        public Payment $payment,
        public ?string $redirectUrl = null,
    ) {}
}
