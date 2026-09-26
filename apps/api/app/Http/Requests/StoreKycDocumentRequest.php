<?php

namespace App\Http\Requests;

use App\Enums\KycDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKycDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('kyc.upload.max_size_kb', 5120), 'mimetypes:application/pdf,image/jpeg,image/png,image/webp'],
            'type' => ['required', Rule::enum(KycDocumentType::class)],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
        ];
    }
}
