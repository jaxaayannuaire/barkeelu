<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'title' => $this->title, 'slug' => $this->slug, 'description' => $this->description, 'goal_amount' => $this->goal_amount, 'currency' => $this->currency, 'status' => $this->status->value, 'fundraising_status' => $this->fundraising_status->value, 'visibility' => $this->visibility->value, 'goal_reached' => $this->goal_reached, 'gross_collected_nominal' => $this->gross_collected_nominal, 'net_collected_nominal' => $this->net_collected_nominal, 'donation_count' => $this->donation_count, 'distinct_donor_count' => $this->distinct_donor_count, 'published_at' => $this->published_at, 'start_at' => $this->start_at, 'end_at' => $this->end_at, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
