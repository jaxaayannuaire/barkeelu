<?php

namespace App\Policies;

use App\Enums\BeneficiaryRepresentativeStatus;
use App\Models\Beneficiary;
use App\Models\User;

class BeneficiaryPolicy
{
    public function view(User $user, Beneficiary $beneficiary): bool
    {
        return $this->canManageGlobally($user) || $beneficiary->created_by_user_id === $user->id || $this->isActiveRepresentative($user, $beneficiary);
    }

    public function update(User $user, Beneficiary $beneficiary): bool
    {
        return $this->view($user, $beneficiary);
    }

    public function manageRepresentatives(User $user, Beneficiary $beneficiary): bool
    {
        return $this->canManageGlobally($user) || $beneficiary->created_by_user_id === $user->id;
    }

    private function canManageGlobally(User $user): bool
    {
        return $user->can('compliance.manage');
    }

    private function isActiveRepresentative(User $user, Beneficiary $beneficiary): bool
    {
        return $beneficiary->representatives()->where('representative_user_id', $user->id)->where('status', BeneficiaryRepresentativeStatus::ACTIVE->value)->where('valid_from', '<=', now())->where(fn ($validity) => $validity->whereNull('valid_until')->orWhere('valid_until', '>', now()))->exists();
    }
}
