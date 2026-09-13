<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'display_name' => $this->display_name, 'type' => $this->type->value, 'status' => $this->status->value, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
