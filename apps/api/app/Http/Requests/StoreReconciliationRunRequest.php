<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReconciliationRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['provider_account_public_id' => ['required', 'uuid'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date', 'after:period_start'], 'source' => ['required', 'string', 'max:191']];
    }
}
