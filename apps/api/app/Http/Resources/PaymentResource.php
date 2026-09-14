<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'donation_public_id' => $this->donation->public_id, 'provider' => $this->provider, 'internal_reference' => $this->internal_reference, 'amount' => $this->amount, 'currency' => $this->currency, 'status' => $this->status->value, 'paid_at' => $this->paid_at, 'created_at' => $this->created_at];
    }
}
