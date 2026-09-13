<?php

namespace App\Policies;

use App\Enums\OrganizationMembershipRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $this->canManageAllOrganizations($user) || $this->hasActiveMembership($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->canManageAllOrganizations($user) || $this->hasActiveMembership(
            $user,
            $organization,
            [OrganizationMembershipRole::OWNER, OrganizationMembershipRole::ADMIN],
        );
    }

    public function archive(User $user, Organization $organization): bool
    {
        return $this->canManageAllOrganizations($user) || $this->hasActiveMembership(
            $user,
            $organization,
            [OrganizationMembershipRole::OWNER],
        );
    }

    private function canManageAllOrganizations(User $user): bool
    {
        return $user->hasPermissionTo('organizations.manage_all', 'web');
    }

    /**
     * @param  array<int, OrganizationMembershipRole>|null  $roles
     */
    private function hasActiveMembership(User $user, Organization $organization, ?array $roles = null): bool
    {
        $query = $organization->memberships()
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembershipStatus::ACTIVE->value);

        if ($roles !== null) {
            $query->whereIn('membership_role', array_map(
                static fn (OrganizationMembershipRole $role): string => $role->value,
                $roles,
            ));
        }

        return $query->exists();
    }
}
