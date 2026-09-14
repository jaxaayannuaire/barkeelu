<?php

namespace App\Models;

use App\Enums\DonationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => DonationStatus::class, 'is_anonymous' => 'boolean', 'paid_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
