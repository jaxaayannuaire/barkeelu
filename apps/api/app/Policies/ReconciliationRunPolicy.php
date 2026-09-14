<?php

namespace App\Policies;

use App\Models\ReconciliationRun;
use App\Models\User;

class ReconciliationRunPolicy
{
    public function view(User $user, ReconciliationRun $run): bool
    {
        return $user->can('finance.operate') || $user->can('finance.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.operate');
    }
}
