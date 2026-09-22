<?php

namespace App\Models;

use App\Enums\KycDocumentAssetRole;
use App\Enums\KycDocumentAssetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocumentAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => KycDocumentAssetRole::class,
            'status' => KycDocumentAssetStatus::class,
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KycDocument::class, 'kyc_document_id');
    }
}
