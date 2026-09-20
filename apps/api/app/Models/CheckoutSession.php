<?php

namespace App\Models;

use App\Enums\CheckoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutSession extends Model
{
    protected $guarded = [];

    protected $hidden = ['donor_snapshot', 'payer_mobile_encrypted'];

    protected function casts(): array
    {
        return [
            'status' => CheckoutStatus::class,
            'fee_snapshot' => 'array',
            'donor_snapshot' => 'array',
            'payer_mobile_encrypted' => 'encrypted',
            'quote_expires_at' => 'datetime',
            'checkout_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_user_id');
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function lastPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'last_payment_id');
    }
}
