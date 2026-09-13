<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ManageCampaignResource extends CampaignResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['payout_status' => $this->payout_status->value, 'available_for_payout' => $this->available_for_payout, 'reserved_for_payout' => $this->reserved_for_payout, 'paid_out_amount' => $this->paid_out_amount];
    }
}
