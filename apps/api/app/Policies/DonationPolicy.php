<?php

namespace App\Policies;

use App\Models\Donation;
use App\Models\User;

class DonationPolicy
{
    public function view(User $user, Donation $donation): bool
    {
        return $donation->donor_user_id === $user->id
            || $donation->campaign->created_by_user_id === $user->id
            || $user->can('moderation.manage');
    }
}
