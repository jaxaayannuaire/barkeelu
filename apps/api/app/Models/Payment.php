<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $guarded = [];

    protected $hidden = ['payer_mobile_encrypted'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'provider_payload' => 'array',
            'provider_checkout_expires_at' => 'datetime',
            'payer_mobile_encrypted' => 'encrypted',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class);
    }
}
