<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryRepresentativeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'representative_user_id' => $this->representative_user_id, 'status' => $this->status->value, 'valid_from' => $this->valid_from, 'valid_until' => $this->valid_until, 'created_at' => $this->created_at];
    }
}
