<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['provider_account_public_id' => ['required', 'uuid'], 'amount' => ['required', 'integer', 'min:1'], 'currency' => ['required', 'in:XOF'], 'idempotency_key' => ['required', 'string', 'max:191']];
    }
}
