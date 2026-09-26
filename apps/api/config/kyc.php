<?php

$configuredMaxSize = filter_var(env('KYC_UPLOAD_MAX_SIZE_KB', 5120), FILTER_VALIDATE_INT);
$maxSizeKb = is_int($configuredMaxSize) && $configuredMaxSize >= 1 && $configuredMaxSize <= 25600
    ? $configuredMaxSize
    : 5120;

$configuredOptimization = filter_var(env('KYC_IMAGE_OPTIMIZATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
$configuredQuality = filter_var(env('KYC_IMAGE_WEBP_QUALITY', 88), FILTER_VALIDATE_INT);
$configuredDimension = filter_var(env('KYC_IMAGE_MAX_DIMENSION', 2400), FILTER_VALIDATE_INT);
$configuredPixels = filter_var(env('KYC_IMAGE_MAX_INPUT_PIXELS', 50000000), FILTER_VALIDATE_INT);

return [
    'upload' => [
        'max_size_kb' => $maxSizeKb,
    ],
    'image' => [
        'optimization_enabled' => $configuredOptimization ?? true,
        'queue' => env('KYC_IMAGE_QUEUE', 'kyc-media'),
        'webp_quality' => is_int($configuredQuality) && $configuredQuality >= 1 && $configuredQuality <= 100 ? $configuredQuality : 88,
        'max_dimension' => is_int($configuredDimension) && $configuredDimension >= 320 && $configuredDimension <= 6000 ? $configuredDimension : 2400,
        'max_input_pixels' => is_int($configuredPixels) && $configuredPixels >= 1_000_000 && $configuredPixels <= 100_000_000 ? $configuredPixels : 50_000_000,
    ],
];
