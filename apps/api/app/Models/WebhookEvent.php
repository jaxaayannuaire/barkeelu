<?php

namespace App\Models;

use App\Enums\WebhookEventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'headers_redacted' => 'array',
            'status' => WebhookEventStatus::class,
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class);
    }
}
