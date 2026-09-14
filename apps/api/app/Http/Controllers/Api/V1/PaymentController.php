<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function show(Payment $payment): PaymentResource
    {
        Gate::authorize('view', $payment);

        return new PaymentResource($payment);
    }
}
