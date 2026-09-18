<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignFundraisingStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Services\Checkout\CheckoutSessionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutSessionController extends Controller
{
    public function store(Request $request, Campaign $campaign, CheckoutSessionService $service): JsonResponse
    {
        abort_unless(Campaign::query()
            ->publiclyViewable()
            ->whereKey($campaign->id)
            ->where('fundraising_status', CampaignFundraisingStatus::OPEN->value)
            ->exists(), 404);

        $request->merge([
            'idempotency_key' => $request->input('idempotency_key') ?? $request->header('Idempotency-Key'),
        ]);
        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', 'in:XOF'],
            'nominal_amount' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $checkout = $service->create($campaign, $request->user(), $validated);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $this->publicPayload($checkout)], 201);
    }

    public function show(CheckoutSession $checkout): JsonResponse
    {
        return response()->json(['data' => $this->publicPayload($checkout)]);
    }

    private function publicPayload(CheckoutSession $checkout): array
    {
        return [
            'public_id' => $checkout->public_id,
            'campaign_public_id' => $checkout->campaign?->public_id,
            'status' => $checkout->status->value,
            'currency' => $checkout->currency,
            'nominal_amount' => $checkout->nominal_amount,
            'total_payable_amount' => $checkout->total_payable_amount,
            'quote_expires_at' => $checkout->quote_expires_at,
            'checkout_expires_at' => $checkout->checkout_expires_at,
            'confirmed_at' => $checkout->confirmed_at,
            'created_at' => $checkout->created_at,
            'updated_at' => $checkout->updated_at,
        ];
    }
}
