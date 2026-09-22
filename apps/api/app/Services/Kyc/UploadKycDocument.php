<?php

namespace App\Services\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEventType;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class UploadKycDocument
{
    private const DISK = 'kyc_private';

    public function upload(KycProfile $profile, User $uploader, UploadedFile $file, KycDocumentType $type, ?string $issuedAt = null, ?string $expiresAt = null): KycDocument
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Le fichier KYC téléversé est invalide.');
        }

        $extension = $file->guessExtension() ?: 'bin';
        $objectKey = 'profiles/'.$profile->public_id.'/'.Str::random(48).'.'.$extension;
        $disk = Storage::disk(self::DISK);
        $disk->put($objectKey, $file->getContent());

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
