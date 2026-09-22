<?php

namespace App\Services\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEventType;
use App\Exceptions\KycFileException;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadKycDocument
{
    private const DISK = 'kyc_private';

    public function __construct(private readonly KycFileInspector $inspector) {}

    public function upload(KycProfile $profile, User $uploader, UploadedFile $file, KycDocumentType $type, ?string $issuedAt = null, ?string $expiresAt = null): KycDocument
    {
        $extension = $this->inspector->inspect($file);
        $objectKey = 'profiles/'.$profile->public_id.'/'.Str::random(48).'.'.$extension;
        $disk = Storage::disk(self::DISK);
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false || ! $disk->put($objectKey, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new KycFileException('KYC_FILE_STORAGE_FAILED', 'Stockage du fichier KYC impossible.', 500);
        }
        fclose($stream);

        try {
            return DB::transaction(function () use ($profile, $uploader, $type, $issuedAt, $expiresAt, $objectKey, $file, $extension): KycDocument {
                $document = KycDocument::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'kyc_profile_id' => $profile->id,
                    'type' => $type,
                    'status' => KycDocumentStatus::UPLOADED,
                    'storage_disk' => self::DISK,
                    'object_key' => $objectKey,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes' => $file->getSize(),
                    'issued_at' => $issuedAt,
                    'expires_at' => $expiresAt,
                    'metadata' => ['extension' => $extension],
                    'uploaded_by_user_id' => $uploader->id,
                ]);

                app(RecordKycReviewEvent::class)->recordDocument($document, KycReviewEventType::DOCUMENT_UPLOADED, KycReviewActorType::HUMAN, $uploader, toStatus: KycDocumentStatus::UPLOADED);

                return $document;
            });
        } catch (Throwable $exception) {
            $disk->delete($objectKey);

            throw $exception;
        }
    }
}
