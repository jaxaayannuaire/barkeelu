<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutRequest;
use App\Http\Resources\PayoutResource;
use App\Models\Campaign;
use App\Models\Payout;
use App\Models\ProviderAccount;
use App\Services\Payouts\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PayoutController extends Controller
{
    public function store(StorePayoutRequest $request, Campaign $campaign, PayoutService $service): JsonResponse
    {
        Gate::authorize('create', Payout::class);
        $account = ProviderAccount::query()->where('public_id', $request->validated('provider_account_public_id'))->firstOrFail();
        $payout = $service->request($campaign, $account, $request->user(), $request->validated());

        return (new PayoutResource($payout))->response()->setStatusCode(201);
    }

    public function show(Payout $payout): PayoutResource
    {
        Gate::authorize('view', $payout);

        return new PayoutResource($payout);
    }

    public function approve(Payout $payout, PayoutService $service): PayoutResource
    {
        Gate::authorize('approve', $payout);

        return new PayoutResource($service->approve($payout, request()->user()));
    }
}
