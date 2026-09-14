<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['nominal_amount' => ['required', 'integer', 'min:1'], 'currency' => ['required', 'in:XOF'], 'idempotency_key' => ['required', 'string', 'max:191'], 'is_anonymous' => ['sometimes', 'boolean'], 'donor_name' => ['nullable', 'string', 'max:255'], 'donor_email' => ['nullable', 'email:rfc', 'max:255']];
    }
}
