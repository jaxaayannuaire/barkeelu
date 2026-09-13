<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKycProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:organization_public_id,beneficiary_public_id', 'prohibits:organization_public_id,beneficiary_public_id'],
            'organization_public_id' => ['nullable', 'uuid', 'exists:organizations,public_id', 'required_without_all:user_id,beneficiary_public_id', 'prohibits:user_id,beneficiary_public_id'],
            'beneficiary_public_id' => ['nullable', 'uuid', 'exists:beneficiaries,public_id', 'required_without_all:user_id,organization_public_id', 'prohibits:user_id,organization_public_id'],
        ];
    }
}
