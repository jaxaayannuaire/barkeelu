<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Refunds\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RefundController extends Controller
{
    public function store(StoreRefundRequest $request, Payment $payment, RefundService $service): JsonResponse
    {
        Gate::authorize('create', Refund::class);
        $refund = $service->request($payment, $request->user(), $request->validated());

        return (new RefundResource($refund))->response()->setStatusCode(201);
    }

    public function show(Refund $refund): RefundResource
    {
        Gate::authorize('view', $refund);

        return new RefundResource($refund);
    }
}
