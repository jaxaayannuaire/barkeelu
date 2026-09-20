<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\DonationResource;
use App\Http\Resources\PaymentResource;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\ProviderAccount;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DonationController extends Controller
{
    public function store(Campaign $campaign): JsonResponse
    {
        return response()->json([
            'message' => 'Endpoint de création directe de Donation déprécié. Utilisez CheckoutSession.',
            'code' => 'DIRECT_DONATION_ENDPOINT_DEPRECATED',
            'replacement' => [
                'method' => 'POST',
                'path' => '/api/v1/campaigns/'.$campaign->public_id.'/checkout-sessions',
            ],
        ], 410);
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
