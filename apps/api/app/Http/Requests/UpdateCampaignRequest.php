<?php

namespace App\Http\Requests;

use App\Enums\CampaignVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'required', 'string'], 'goal_amount' => ['sometimes', 'required', 'integer', 'min:1'], 'currency' => ['prohibited'], 'visibility' => ['sometimes', 'required', Rule::enum(CampaignVisibility::class), 'not_in:TARGETED'], 'start_at' => ['nullable', 'date'], 'end_at' => ['nullable', 'date', 'after:start_at']];
    }
}
