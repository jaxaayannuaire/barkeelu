<?php

namespace App\Services\Kyc;

use App\Enums\KycImageProcessingErrorCode;
use App\Exceptions\KycImageProcessingException;
use App\Models\KycDocument;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

final class KycImageDerivativeProcessor
{
    public const PROCESSOR = 'image_webp';

    public const PROCESSOR_VERSION = 'v1';

    public const OUTPUT_MIME = 'image/webp';

    public function generate(KycDocument $document, string $targetDisk, string $targetObjectKey): KycGeneratedImageAsset
    {
        if (! config('kyc.image.optimization_enabled', true)) {
            throw $this->error(KycImageProcessingErrorCode::PROCESSING_DISABLED, 'Optimisation image désactivée.');
        }

        $this->assertTargetKey($targetObjectKey);

        if ($targetDisk === $document->storage_disk && $targetObjectKey === $document->object_key) {
            throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'La destination ne peut pas remplacer le master.');
        }

        if (! in_array($document->mime_type, ['image/jpeg', 'image/png'], true)) {
            throw $this->error(KycImageProcessingErrorCode::MASTER_UNSUPPORTED, 'Type de master image non supporté.');
        }

        $disk = Storage::disk($document->storage_disk);
        if (! $disk->exists($document->object_key)) {
            throw $this->error(KycImageProcessingErrorCode::MASTER_NOT_FOUND, 'Master image indisponible.');
        }

        $inputPath = $this->temporaryPath('barkeelu-kyc-input-');
        $outputPath = $this->temporaryPath('barkeelu-kyc-output-');
        $destinationAttempted = false;

        try {
            $this->copyToFile($disk, $document->object_key, $inputPath);
            $this->assertImagePreflight($inputPath, $document->mime_type);

            $manager = new ImageManager(new Driver);
            try {
                $image = $manager->read($inputPath);
            } catch (Throwable $exception) {
                throw $this->error(KycImageProcessingErrorCode::DECODE_FAILED, 'Master image illisible.', $exception);
            }

            $image->scaleDown(
                width: (int) config('kyc.image.max_dimension', 2400),
                height: (int) config('kyc.image.max_dimension', 2400),
            );

            try {
                $encoded = $image->toWebp((int) config('kyc.image.webp_quality', 88));
                $encoded->save($outputPath);
            } catch (Throwable $exception) {
                throw $this->error(KycImageProcessingErrorCode::ENCODE_FAILED, 'Encodage image échoué.', $exception);
            }

            $size = filesize($outputPath);
            $hash = hash_file('sha256', $outputPath);
            if (! is_int($size) || $size <= 0 || ! is_string($hash) || ! $this->isWebp($outputPath)) {
                throw $this->error(KycImageProcessingErrorCode::ENCODE_FAILED, 'Dérivé image invalide.');
            }

            $dimensions = getimagesize($outputPath);
            if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
                throw $this->error(KycImageProcessingErrorCode::ENCODE_FAILED, 'Dimensions du dérivé invalides.');
            }

            try {
                $target = Storage::disk($targetDisk);
            } catch (Throwable $exception) {
                throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'Stockage du dérivé indisponible.', $exception);
            }
            $stream = fopen($outputPath, 'rb');
            if ($stream === false) {
                throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'Dérivé image impossible à ouvrir.');
            }

            try {
                $destinationAttempted = true;
                if (! $target->put($targetObjectKey, $stream)) {
                    throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'Écriture du dérivé échouée.');
                }
            } catch (KycImageProcessingException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'Écriture du dérivé échouée.', $exception);
            } finally {
                fclose($stream);
            }

            return new KycGeneratedImageAsset(
                storageDisk: $targetDisk,
                objectKey: $targetObjectKey,
                mimeType: self::OUTPUT_MIME,
                sizeBytes: $size,
                sha256: strtolower($hash),
                width: (int) $dimensions[0],
                height: (int) $dimensions[1],
                processor: self::PROCESSOR,
                processorVersion: self::PROCESSOR_VERSION,
            );
        } catch (KycImageProcessingException $exception) {
            if ($destinationAttempted) {
                try {
                    Storage::disk($targetDisk)->delete($targetObjectKey);
                } catch (Throwable) {
                    // Nettoyage best effort ; master original reste intact.
                }
            }
            throw $exception;
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    private function copyToFile(Filesystem $disk, string $key, string $path): void
    {
        $source = $disk->readStream($key);
        $destination = fopen($path, 'wb');
        if ($source === false || $destination === false) {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($destination)) {
                fclose($destination);
            }
            throw $this->error(KycImageProcessingErrorCode::MASTER_NOT_FOUND, 'Lecture du master échouée.');
        }

        try {
            if (stream_copy_to_stream($source, $destination) === false) {
                throw $this->error(KycImageProcessingErrorCode::MASTER_NOT_FOUND, 'Lecture du master échouée.');
            }
        } catch (KycImageProcessingException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->error(KycImageProcessingErrorCode::MASTER_NOT_FOUND, 'Lecture du master échouée.', $exception);
        } finally {
            fclose($source);
            fclose($destination);
        }
    }

    private function assertImagePreflight(string $path, string $expectedMime): void
    {
        $dimensions = @getimagesize($path, $info);
        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1 || ($dimensions['mime'] ?? null) !== $expectedMime) {
            throw $this->error(KycImageProcessingErrorCode::INVALID, 'Master image invalide.');
        }

        $width = (int) $dimensions[0];
        $height = (int) $dimensions[1];
        $maxPixels = (int) config('kyc.image.max_input_pixels', 50_000_000);
        if ($width > intdiv(PHP_INT_MAX, $height) || $width * $height > $maxPixels) {
            throw $this->error(KycImageProcessingErrorCode::PIXEL_LIMIT_EXCEEDED, 'Limite de pixels dépassée.');
        }
    }

    private function assertTargetKey(string $key): void
    {
        if ($key === '' || str_contains($key, "\0") || str_contains($key, '\\') || str_starts_with($key, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $key) || in_array('..', explode('/', $key), true)) {
            throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'Destination du dérivé invalide.');
        }
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw $this->error(KycImageProcessingErrorCode::STORAGE_FAILED, 'Fichier temporaire impossible à créer.');
        }

        return $path;
    }

    private function isWebp(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 12);
        fclose($handle);

        return is_string($header) && strlen($header) === 12 && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP';
    }

    private function error(KycImageProcessingErrorCode $code, string $message, ?Throwable $previous = null): KycImageProcessingException
    {
        return new KycImageProcessingException($code, $message, $previous);
    }
}
