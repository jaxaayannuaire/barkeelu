<?php

namespace App\Http\Requests;

use App\Enums\BeneficiaryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(BeneficiaryType::class)],
            'linked_user_id' => ['nullable', 'integer', 'exists:users,id', 'prohibits:linked_organization_public_id'],
            'linked_organization_public_id' => ['nullable', 'uuid', 'exists:organizations,public_id', 'prohibits:linked_user_id'],
        ];
    }
}
