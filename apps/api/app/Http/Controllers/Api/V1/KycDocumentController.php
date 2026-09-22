<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class KycDocumentController extends Controller
{
    public function download(KycDocument $kycDocument)
    {
        Gate::authorize('view', $kycDocument);
        $disk = Storage::disk($kycDocument->storage_disk);
        if (! $disk->exists($kycDocument->object_key)) {
            return response()->json(['message' => 'Document KYC indisponible.', 'code' => 'KYC_DOCUMENT_NOT_FOUND'], 404);
        }

        $stream = $disk->readStream($kycDocument->object_key);
        if ($stream === false) {
            return response()->json(['message' => 'Document KYC indisponible.', 'code' => 'KYC_DOCUMENT_NOT_FOUND'], 404);
        }

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 'document-kyc-'.$kycDocument->public_id.'.'.$this->extension($kycDocument->mime_type), [
            'Content-Type' => $kycDocument->mime_type,
            'Content-Length' => (string) $kycDocument->size_bytes,
        ]);
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
    }
}
