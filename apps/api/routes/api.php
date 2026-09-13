<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\BeneficiaryController;
use App\Http\Controllers\Api\V1\KycProfileController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));

    Route::post('/auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:auth-token');

    Route::middleware('auth:sanctum')->group(function (): void {
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
