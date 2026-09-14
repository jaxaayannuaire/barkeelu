<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    public function view(User $user, Refund $refund): bool
    {
        return $refund->requested_by_user_id === $user->id
            || $user->can('finance.operate')
            || $user->can('finance.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.operate');
    }
}
