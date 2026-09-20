<?php

namespace App\Data\Donations;

final readonly class ConfirmedDonationData
{
    public function __construct(
        public int $campaignId,
        public ?int $donorUserId,
        public array $donorSnapshot,
        public int $nominalAmount,
        public int $platformFeeAmount,
        public int $payoutProvisionAmount,
        public int $totalPayableAmount,
        public string $currency,
        public string $idempotencyKey,
        public string $contentHash,
        public ?int $createdByUserId,
        public string $sourceContext,
    ) {}
}
