<?php

namespace App\Http\Requests;

use App\Enums\CampaignVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string'], 'beneficiary_public_id' => ['required', 'uuid', 'exists:beneficiaries,public_id'], 'goal_amount' => ['required', 'integer', 'min:1'], 'currency' => ['required', 'in:XOF'], 'visibility' => ['required', Rule::enum(CampaignVisibility::class), 'not_in:TARGETED'], 'owner_organization_public_id' => ['nullable', 'uuid', 'exists:organizations,public_id'], 'start_at' => ['nullable', 'date'], 'end_at' => ['nullable', 'date', 'after:start_at']];
    }
}
