<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->donation->donor_user_id === $user->id
            || $payment->donation->campaign->created_by_user_id === $user->id
            || $user->can('moderation.manage');
    }
}
