<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'payment_public_id' => $this->payment->public_id, 'amount' => $this->amount, 'currency' => $this->currency, 'status' => $this->status->value, 'requested_at' => $this->requested_at, 'processed_at' => $this->processed_at, 'created_at' => $this->created_at];
    }
}
