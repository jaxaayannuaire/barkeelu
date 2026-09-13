<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'status' => $this->status->value, 'risk_level' => $this->risk_level->value, 'submitted_at' => $this->submitted_at, 'reviewed_at' => $this->reviewed_at, 'rejection_reason' => $this->rejection_reason, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
