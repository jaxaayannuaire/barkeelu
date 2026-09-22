<?php

namespace App\Http\Requests;

use App\Enums\KycRiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KycRiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'risk_level' => ['required', Rule::enum(KycRiskLevel::class)],
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9_]+$/'],
            'reason_text' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
