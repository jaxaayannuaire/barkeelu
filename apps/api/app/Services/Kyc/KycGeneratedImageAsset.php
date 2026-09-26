<?php

namespace App\Services\Kyc;

final readonly class KycGeneratedImageAsset
{
    public function __construct(
        public string $storageDisk,
        public string $objectKey,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public int $width,
        public int $height,
        public string $processor,
        public string $processorVersion,
    ) {}
}
