<?php

namespace App\Models;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Beneficiary extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => BeneficiaryType::class, 'status' => BeneficiaryStatus::class];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function linkedOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'linked_organization_id');
    }

    public function representatives(): HasMany
    {
        return $this->hasMany(BeneficiaryRepresentative::class);
    }

    public function kycProfile(): HasOne
    {
        return $this->hasOne(KycProfile::class);
    }
}
