<?php

namespace App\Jobs\Kyc;

use App\Enums\KycDocumentAssetRole;
use App\Enums\KycDocumentAssetStatus;
use App\Models\KycDocumentAsset;
use App\Services\Kyc\KycImageDerivativeProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GenerateKycDocumentDerivativeJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $timeout = 180;

    public int $uniqueFor = 3600;

    public function __construct(public int $assetId) {}

    public function uniqueId(): string
    {
        return 'kyc-image-derivative:'.$this->assetId;
    }

    public function handle(KycImageDerivativeProcessor $processor): void
    {
        $context = DB::transaction(function (): ?array {
            $asset = KycDocumentAsset::query()->with('document')->lockForUpdate()->find($this->assetId);
            if ($asset === null || $asset->status === KycDocumentAssetStatus::READY || $asset->status !== KycDocumentAssetStatus::PENDING) {
                return null;
            }

            if ($asset->role !== KycDocumentAssetRole::OPTIMIZED
                || $asset->processor !== KycImageDerivativeProcessor::PROCESSOR
                || $asset->processor_version !== KycImageDerivativeProcessor::PROCESSOR_VERSION
                || $asset->document === null) {
                return null;
            }

            return [
                'document' => $asset->document,
                'target_disk' => $asset->document->storage_disk,
                'target_key' => $this->targetObjectKey($asset),
            ];
        });

        if ($context === null) {
            return;
        }

        $generated = $processor->generate($context['document'], $context['target_disk'], $context['target_key']);

        try {
            DB::transaction(function () use ($generated): void {
                $asset = KycDocumentAsset::query()->lockForUpdate()->findOrFail($this->assetId);
                if ($asset->status === KycDocumentAssetStatus::READY) {
                    return;
                }
                if ($asset->status !== KycDocumentAssetStatus::PENDING) {
                    return;
                }

                $asset->forceFill([
                    'status' => KycDocumentAssetStatus::READY,
                    'storage_disk' => $generated->storageDisk,
                    'object_key' => $generated->objectKey,
                    'mime_type' => $generated->mimeType,
                    'size_bytes' => $generated->sizeBytes,
                    'sha256' => $generated->sha256,
                    'width' => $generated->width,
                    'height' => $generated->height,
                    'processed_at' => now(),
                ])->save();
            });
        } catch (Throwable $exception) {
            $this->deleteGeneratedDerivativeUnlessReady($generated->storageDisk, $generated->objectKey);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $cleanup = DB::transaction(function (): ?array {
            $asset = KycDocumentAsset::query()->with('document')->lockForUpdate()->find($this->assetId);
            if ($asset === null || $asset->status === KycDocumentAssetStatus::READY) {
                return null;
            }

            $asset->forceFill([
                'status' => KycDocumentAssetStatus::FAILED,
                'storage_disk' => null,
                'object_key' => null,
                'mime_type' => null,
                'size_bytes' => null,
                'sha256' => null,
                'width' => null,
                'height' => null,
                'processed_at' => now(),
            ])->save();

            if ($asset->document === null) {
                return null;
            }

            return [$asset->document->storage_disk, $this->targetObjectKey($asset)];
        });

        if ($cleanup !== null) {
            try {
                Storage::disk($cleanup[0])->delete($cleanup[1]);
            } catch (Throwable) {
                // Nettoyage best effort ; le master reste intact.
            }
        }
    }

    private function targetObjectKey(KycDocumentAsset $asset): string
    {
        return 'profiles/'.$asset->document->profile->public_id.'/derivatives/'.$asset->document->public_id.'/'.$asset->public_id.'.webp';
    }

    private function deleteGeneratedDerivativeUnlessReady(string $disk, string $key): void
    {
        try {
            if (KycDocumentAsset::query()->whereKey($this->assetId)->where('status', KycDocumentAssetStatus::READY->value)->exists()) {
                return;
            }

            Storage::disk($disk)->delete($key);
        } catch (Throwable) {
            // Nettoyage best effort ; le master reste intact.
        }
    }
}
