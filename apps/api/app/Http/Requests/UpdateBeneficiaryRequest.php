<?php

namespace App\Http\Requests;

use App\Enums\BeneficiaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['display_name' => ['sometimes', 'required', 'string', 'max:255'], 'type' => ['sometimes', 'required', Rule::enum(BeneficiaryType::class)]];
    }
}
