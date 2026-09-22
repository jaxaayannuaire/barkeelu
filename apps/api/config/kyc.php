<?php

$configuredMaxSize = filter_var(env('KYC_UPLOAD_MAX_SIZE_KB', 5120), FILTER_VALIDATE_INT);
$maxSizeKb = is_int($configuredMaxSize) && $configuredMaxSize >= 1 && $configuredMaxSize <= 25600
    ? $configuredMaxSize
    : 5120;

return [
    'upload' => [
        'max_size_kb' => $maxSizeKb,
    ],
];
