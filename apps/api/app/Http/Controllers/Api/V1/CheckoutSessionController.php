<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignFundraisingStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CheckoutSession;
use App\Services\Checkout\CheckoutConfirmationService;
use App\Services\Checkout\CheckoutQuoteService;
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

    public function quote(Request $request, CheckoutSession $checkout, CheckoutQuoteService $service): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'show_name' => ['sometimes', 'boolean'],
            'show_amount' => ['sometimes', 'boolean'],
        ]);

        try {
            $quoted = $service->quote($checkout, $validated);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $this->quotePayload($quoted)]);
    }

    public function confirm(Request $request, CheckoutSession $checkout, CheckoutConfirmationService $service): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:255'],
        ]);

        try {
            $confirmed = $service->confirm($checkout, $validated);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => array_merge($this->publicPayload($confirmed), [
            'donation_public_id' => $confirmed->donation?->public_id,
        ])]);
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

    private function quotePayload(CheckoutSession $checkout): array
    {
        return array_merge($this->publicPayload($checkout), [
            'fees' => collect($checkout->fee_snapshot['fees'] ?? [])->map(fn (array $fee): array => [
                'fee_type' => $fee['fee_type'],
                'calculation_base_amount' => $fee['calculation_base_amount'],
                'calculated_amount' => $fee['calculated_amount'],
                'currency' => $fee['currency'],
            ])->values()->all(),
        ]);
    }
}
