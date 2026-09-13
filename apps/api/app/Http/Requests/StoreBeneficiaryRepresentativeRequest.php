<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiaryRepresentativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['representative_user_id' => ['required', 'integer', 'exists:users,id'], 'valid_from' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after:valid_from']];
    }
}
