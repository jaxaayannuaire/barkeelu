<?php

namespace App\Policies;

use App\Models\ReconciliationItem;
use App\Models\User;

class ReconciliationItemPolicy
{
    public function resolve(User $user, ReconciliationItem $item): bool
    {
        return $user->can('finance.approve');
    }
}
