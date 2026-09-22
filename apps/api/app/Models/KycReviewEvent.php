<?php

namespace App\Models;

use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEntityType;
use App\Enums\KycReviewEventType;
use App\Enums\KycRiskLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycReviewEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entity_type' => KycReviewEntityType::class,
            'event_type' => KycReviewEventType::class,
            'risk_level_before' => KycRiskLevel::class,
            'risk_level_after' => KycRiskLevel::class,
            'actor_type' => KycReviewActorType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(KycProfile::class, 'kyc_profile_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KycDocument::class, 'kyc_document_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
