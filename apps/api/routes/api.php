<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BeneficiaryController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\CheckoutSessionController;
use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\KycProfileController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\ReconciliationController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::pattern('kycProfile', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}');

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));

    Route::post('/auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:auth-token');

    Route::get('campaigns', [CampaignController::class, 'index']);
    Route::get('campaigns/by-slug/{slug}', [CampaignController::class, 'publicShow']);
    Route::post('campaigns/{campaign}/checkout-sessions', [CheckoutSessionController::class, 'store']);
    Route::get('checkout-sessions/{checkout}', [CheckoutSessionController::class, 'show']);
    Route::post('checkout-sessions/{checkout}/quote', [CheckoutSessionController::class, 'quote']);
    Route::post('checkout-sessions/{checkout}/confirm', [CheckoutSessionController::class, 'confirm']);
    Route::post('checkout-sessions/{checkout}/payments', [CheckoutSessionController::class, 'payments']);
    Route::get('checkout-sessions/{checkout}/status', [CheckoutSessionController::class, 'status']);
    Route::post('webhooks/{provider}', [WebhookController::class, 'store'])->middleware('throttle:webhook-wave');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me/campaigns', [CampaignController::class, 'mine']);
        Route::post('campaigns', [CampaignController::class, 'store']);
        Route::post('campaigns/{campaign}/donations', [DonationController::class, 'store']);
        Route::get('donations/{donation}', [DonationController::class, 'show']);
        Route::post('donations/{donation}/payments', [DonationController::class, 'storePayment']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::post('payments/{payment}/refunds', [RefundController::class, 'store']);
        Route::get('refunds/{refund}', [RefundController::class, 'show']);
        Route::post('campaigns/{campaign}/payouts', [PayoutController::class, 'store']);
        Route::get('payouts/{payout}', [PayoutController::class, 'show']);
        Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve']);
        Route::post('reconciliation-runs', [ReconciliationController::class, 'store']);
        Route::get('reconciliation-runs/{run}', [ReconciliationController::class, 'show']);
        Route::post('reconciliation-items/{item}/resolve', [ReconciliationController::class, 'resolve']);
        Route::patch('manage/campaigns/{campaign}', [CampaignController::class, 'update']);
        Route::post('manage/campaigns/{campaign}/submit', [CampaignController::class, 'submit']);
        Route::post('manage/campaigns/{campaign}/review', [CampaignController::class, 'review']);
        Route::apiResource('organizations', OrganizationController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::apiResource('beneficiaries', BeneficiaryController::class)
            ->only(['index', 'store', 'show', 'update']);
        Route::get('beneficiaries/{beneficiary}/representatives', [BeneficiaryController::class, 'representatives']);
        Route::post('beneficiaries/{beneficiary}/representatives', [BeneficiaryController::class, 'storeRepresentative']);

        Route::post('kyc/profiles', [KycProfileController::class, 'store']);
        Route::get('kyc/profiles/{kycProfile}', [KycProfileController::class, 'show']);
        Route::post('kyc/profiles/{kycProfile}/documents', [KycProfileController::class, 'storeDocument']);
        Route::post('kyc/profiles/{kycProfile}/submit', [KycProfileController::class, 'submit']);
        Route::post('kyc/profiles/{kycProfile}/review/start', [KycProfileController::class, 'startReview']);
        Route::post('kyc/profiles/{kycProfile}/verify', [KycProfileController::class, 'verify']);
        Route::post('kyc/profiles/{kycProfile}/reject', [KycProfileController::class, 'reject']);
        Route::post('kyc/profiles/{kycProfile}/suspend', [KycProfileController::class, 'suspend']);
        Route::post('kyc/profiles/{kycProfile}/expire', [KycProfileController::class, 'expire']);
        Route::post('kyc/profiles/{kycProfile}/reopen', [KycProfileController::class, 'reopen']);
        Route::post('kyc/profiles/{kycProfile}/risk', [KycProfileController::class, 'changeRisk']);

        Route::get('/auth/user', fn () => response()->json([
            'id' => request()->user()->id,
            'name' => request()->user()->name,
            'email' => request()->user()->email,
        ]));
        Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);
    });
});
