<?php

namespace App\Models;

use App\Enums\ProviderEnvironment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['environment' => ProviderEnvironment::class, 'is_active' => 'boolean'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }
}
