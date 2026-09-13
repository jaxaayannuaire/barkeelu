<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'type' => $this->type->value, 'status' => $this->status->value, 'mime_type' => $this->mime_type, 'size_bytes' => $this->size_bytes, 'issued_at' => $this->issued_at, 'expires_at' => $this->expires_at, 'created_at' => $this->created_at];
    }
}
