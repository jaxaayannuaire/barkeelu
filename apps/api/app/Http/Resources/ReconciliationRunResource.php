<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->public_id, 'period_start' => $this->period_start, 'period_end' => $this->period_end, 'source' => $this->source, 'status' => $this->status, 'started_at' => $this->started_at, 'completed_at' => $this->completed_at, 'created_at' => $this->created_at];
    }
}
