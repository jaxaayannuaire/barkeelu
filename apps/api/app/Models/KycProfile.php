<?php

namespace App\Models;

use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => KycStatus::class, 'risk_level' => KycRiskLevel::class, 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }
}
