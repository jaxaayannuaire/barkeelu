<?php

namespace App\Models;

use App\Enums\BeneficiaryRepresentativeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeneficiaryRepresentative extends Model
{
    protected $fillable = ['beneficiary_id', 'representative_user_id', 'status', 'valid_from', 'valid_until', 'created_by_user_id', 'ended_by_user_id'];

    protected function casts(): array
    {
        return ['status' => BeneficiaryRepresentativeStatus::class, 'valid_from' => 'datetime', 'valid_until' => 'datetime'];
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_user_id');
    }
}
