<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDonationRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\DonationResource;
use App\Http\Resources\PaymentResource;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\ProviderAccount;
use App\Services\Donations\DonationService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DonationController extends Controller
{
    public function store(StoreDonationRequest $request, Campaign $campaign, DonationService $service): JsonResponse
    {
        abort_unless($campaign->status->value === 'PUBLISHED' && $campaign->fundraising_status->value === 'OPEN', 422);
        $donation = $service->create($campaign, $request->user(), $request->validated());

        return (new DonationResource($donation))->response()->setStatusCode(201);
    }

    public function show(Donation $donation): DonationResource
    {
        Gate::authorize('view', $donation);

        return new DonationResource($donation);
    }

    public function storePayment(StorePaymentRequest $request, Donation $donation, PaymentService $service): JsonResponse
    {
        Gate::authorize('view', $donation);
        $account = ProviderAccount::query()->where('public_id', $request->validated('provider_account_public_id'))->firstOrFail();
        $payment = $service->create($donation, $account, $request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }
}
