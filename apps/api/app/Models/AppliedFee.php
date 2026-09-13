<?php

namespace App\Models;

use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppliedFee extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fee_type' => FeeType::class];
    }

    public function feePolicy(): BelongsTo
    {
        return $this->belongsTo(FeePolicy::class);
    }
}
