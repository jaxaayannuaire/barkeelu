<?php

namespace App\Policies;

use App\Models\Payout;
use App\Models\User;

class PayoutPolicy
{
    public function view(User $user, Payout $payout): bool
    {
        return $payout->requested_by_user_id === $user->id
            || $user->can('finance.operate')
            || $user->can('finance.approve');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.operate');
    }

    public function approve(User $user, Payout $payout): bool
    {
        return $user->can('finance.approve') && $payout->requested_by_user_id !== $user->id;
    }
}
