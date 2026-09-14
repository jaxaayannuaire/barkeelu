<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'campaign_public_id' => $this->campaign->public_id, 'amount' => $this->amount, 'currency' => $this->currency, 'status' => $this->status->value, 'destination_snapshot' => $this->destination_snapshot, 'requested_at' => $this->requested_at, 'approved_at' => $this->approved_at, 'processed_at' => $this->processed_at, 'created_at' => $this->created_at];
    }
}
