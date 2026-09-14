<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BeneficiaryController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\DonationController;
use App\Http\Controllers\Api\V1\KycProfileController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));

    Route::post('/auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:auth-token');

    Route::get('campaigns', [CampaignController::class, 'index']);
    Route::get('campaigns/by-slug/{slug}', [CampaignController::class, 'publicShow']);
    Route::post('webhooks/{provider}', [WebhookController::class, 'store'])->middleware('throttle:auth-token');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me/campaigns', [CampaignController::class, 'mine']);
        Route::post('campaigns', [CampaignController::class, 'store']);
        Route::post('campaigns/{campaign}/donations', [DonationController::class, 'store']);
        Route::get('donations/{donation}', [DonationController::class, 'show']);
        Route::post('donations/{donation}/payments', [DonationController::class, 'storePayment']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
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

        Route::get('/auth/user', fn () => response()->json([
            'id' => request()->user()->id,
            'name' => request()->user()->name,
            'email' => request()->user()->email,
        ]));
        Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);
    });
});
