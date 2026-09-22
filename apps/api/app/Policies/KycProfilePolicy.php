<?php

namespace App\Policies;

use App\Enums\BeneficiaryRepresentativeStatus;
use App\Enums\OrganizationMembershipRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Beneficiary;
use App\Models\KycProfile;
use App\Models\Organization;
use App\Models\User;

class KycProfilePolicy
{
    public function view(User $user, KycProfile $profile): bool
    {
        return $this->canManage($user, $profile);
    }

    public function create(User $user, User|Organization|Beneficiary $subject): bool
    {
        return $this->canManageSubject($user, $subject);
    }

    public function uploadDocument(User $user, KycProfile $profile): bool
    {
        return $this->canManage($user, $profile);
    }

    public function submit(User $user, KycProfile $profile): bool
    {
        return $this->canManage($user, $profile);
    }

    public function startReview(User $user, KycProfile $profile): bool
    {
        return $this->isCompliance($user);
    }

    public function verify(User $user, KycProfile $profile): bool
    {
        return $this->isCompliance($user);
    }

    public function reject(User $user, KycProfile $profile): bool
    {
        return $this->isCompliance($user);
    }

    public function suspend(User $user, KycProfile $profile): bool
    {
        return $this->isCompliance($user);
    }

    public function expire(User $user, KycProfile $profile): bool
    {
        return $this->isCompliance($user);
    }

    public function reopen(User $user, KycProfile $profile): bool
    {
        return $profile->status->value === 'SUSPENDED'
            ? $this->isCompliance($user)
            : $this->canManage($user, $profile);
    }

    public function changeRisk(User $user, KycProfile $profile): bool
    {
        return $this->isCompliance($user);
    }

    private function canManage(User $user, KycProfile $profile): bool
    {
        if ($this->isCompliance($user)) {
            return true;
        }

        return $profile->user_id === $user->id || ($profile->organization && $this->canManageOrganization($user, $profile->organization)) || ($profile->beneficiary && $this->canManageBeneficiary($user, $profile->beneficiary));
    }

    private function canManageSubject(User $user, User|Organization|Beneficiary $subject): bool
    {
        if ($this->isCompliance($user)) {
            return true;
        }
        if ($subject instanceof User) {
            return $subject->is($user);
        }
        if ($subject instanceof Organization) {
            return $this->canManageOrganization($user, $subject);
        }

        return $this->canManageBeneficiary($user, $subject);
    }

    private function canManageOrganization(User $user, Organization $organization): bool
    {
        return $organization->memberships()->where('user_id', $user->id)->where('status', OrganizationMembershipStatus::ACTIVE->value)->whereIn('membership_role', [OrganizationMembershipRole::OWNER->value, OrganizationMembershipRole::ADMIN->value])->exists();
    }

    private function canManageBeneficiary(User $user, Beneficiary $beneficiary): bool
    {
        return $beneficiary->created_by_user_id === $user->id || $beneficiary->representatives()->where('representative_user_id', $user->id)->where('status', BeneficiaryRepresentativeStatus::ACTIVE->value)->where('valid_from', '<=', now())->where(fn ($validity) => $validity->whereNull('valid_until')->orWhere('valid_until', '>', now()))->exists();
    }

    private function isCompliance(User $user): bool
    {
        return $user->can('compliance.manage');
    }
}
