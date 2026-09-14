<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DonationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'campaign_public_id' => $this->campaign->public_id, 'nominal_amount' => $this->nominal_amount, 'platform_fee_amount' => $this->platform_fee_amount, 'payout_provision_amount' => $this->payout_provision_amount, 'total_payable_amount' => $this->total_payable_amount, 'currency' => $this->currency, 'status' => $this->status->value, 'is_anonymous' => $this->is_anonymous, 'created_at' => $this->created_at];
    }
}
