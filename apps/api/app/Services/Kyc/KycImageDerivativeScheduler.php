<?php

namespace App\Services\Kyc;

use App\Enums\KycDocumentAssetRole;
use App\Enums\KycDocumentAssetStatus;
use App\Jobs\Kyc\GenerateKycDocumentDerivativeJob;
use App\Models\KycDocument;
use App\Models\KycDocumentAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KycImageDerivativeScheduler
{
    public function schedule(KycDocument $document): ?KycDocumentAsset
    {
        if (! config('kyc.image.optimization_enabled', true)) {
            return null;
        }

        if (! in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        $asset = DB::transaction(function () use ($document): KycDocumentAsset {
            $asset = KycDocumentAsset::query()->createOrFirst(
                [
                    'kyc_document_id' => $document->id,
                    'role' => KycDocumentAssetRole::OPTIMIZED,
                    'processor' => KycImageDerivativeProcessor::PROCESSOR,
                    'processor_version' => KycImageDerivativeProcessor::PROCESSOR_VERSION,
                ],
                [
                    'public_id' => (string) Str::uuid(),
                    'status' => KycDocumentAssetStatus::PENDING,
                ],
            );

            $asset = KycDocumentAsset::query()->lockForUpdate()->findOrFail($asset->id);
            if ($asset->status === KycDocumentAssetStatus::FAILED) {
                $asset->forceFill([
                    'status' => KycDocumentAssetStatus::PENDING,
                    'storage_disk' => null,
                    'object_key' => null,
                    'mime_type' => null,
                    'size_bytes' => null,
                    'sha256' => null,
                    'width' => null,
                    'height' => null,
                    'processed_at' => null,
                ])->save();
            }

            return $asset;
        });

        if ($asset->status === KycDocumentAssetStatus::READY) {
            return $asset;
        }

        GenerateKycDocumentDerivativeJob::dispatch($asset->id)
            ->onConnection(config('queue.default'))
            ->onQueue(config('kyc.image.queue', 'kyc-media'));

        return $asset;
    }
}
