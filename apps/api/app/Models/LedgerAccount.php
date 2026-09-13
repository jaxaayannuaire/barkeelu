<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'account_type' => LedgerAccountType::class,
            'active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
