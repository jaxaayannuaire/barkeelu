<?php

use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));

    Route::post('/auth/token', [AuthTokenController::class, 'store'])
        ->middleware('throttle:auth-token');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::apiResource('organizations', OrganizationController::class)
            ->only(['index', 'store', 'show', 'update']);

        Route::get('/auth/user', fn () => response()->json([
            'id' => request()->user()->id,
            'name' => request()->user()->name,
            'email' => request()->user()->email,
        ]));
        Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);
    });
});
