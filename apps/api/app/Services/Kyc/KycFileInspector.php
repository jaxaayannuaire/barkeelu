<?php

namespace App\Services\Kyc;

use App\Exceptions\KycFileException;
use Illuminate\Http\UploadedFile;

class KycFileInspector
{
    public function inspect(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new KycFileException('KYC_FILE_INVALID', 'Fichier KYC invalide.');
        }

        $size = $file->getSize();
        $maxBytes = (int) config('kyc.upload.max_size_kb', 5120) * 1024;
        if ($size === false || $size > $maxBytes) {
            throw new KycFileException('KYC_FILE_TOO_LARGE', 'Fichier KYC trop volumineux.');
        }

        $mime = $file->getMimeType();
        $signatures = [
            'application/pdf' => ['extension' => 'pdf', 'bytes' => '%PDF-'],
            'image/jpeg' => ['extension' => 'jpg', 'bytes' => "\xFF\xD8\xFF"],
            'image/png' => ['extension' => 'png', 'bytes' => "\x89PNG\x0D\x0A\x1A\x0A"],
            'image/webp' => ['extension' => 'webp', 'bytes' => null],
        ];
        if (! isset($signatures[$mime])) {
            throw new KycFileException('KYC_FILE_TYPE_UNSUPPORTED', 'Type de fichier KYC non supporté.');
        }

        $handle = @fopen($file->getRealPath(), 'rb');
        $signature = $handle === false ? false : fread($handle, 16);
        if (is_resource($handle)) {
            fclose($handle);
        }
        $validSignature = $mime === 'image/webp'
            ? $this->isWebpSignature($signature)
            : is_string($signature) && str_starts_with($signature, $signatures[$mime]['bytes']);
        if (! $validSignature) {
            throw new KycFileException('KYC_FILE_SIGNATURE_INVALID', 'Signature de fichier KYC invalide.');
        }

        return $signatures[$mime]['extension'];
    }

    private function isWebpSignature(string|false $header): bool
    {
        return is_string($header)
            && strlen($header) >= 12
            && substr($header, 0, 4) === 'RIFF'
            && substr($header, 8, 4) === 'WEBP';
    }
}
