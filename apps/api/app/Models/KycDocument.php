<?php

namespace App\Models;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycDocument extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => KycDocumentType::class, 'status' => KycDocumentStatus::class, 'issued_at' => 'datetime', 'expires_at' => 'datetime', 'reviewed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(KycProfile::class, 'kyc_profile_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function reviewEvents(): HasMany
    {
        return $this->hasMany(KycReviewEvent::class);
    }
}
