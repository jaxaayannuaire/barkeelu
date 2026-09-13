<?php

namespace App\Models;

use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeePolicy extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fee_type' => FeeType::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function appliedFees(): HasMany
    {
        return $this->hasMany(AppliedFee::class);
    }
}
