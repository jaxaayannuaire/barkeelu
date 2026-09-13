<?php

namespace App\Policies;

use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Enums\OrganizationMembershipRole;
use App\Enums\OrganizationMembershipStatus;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function view(?User $user, Campaign $campaign): bool
    {
        return $this->isPublic($campaign) || ($user && ($this->owns($user, $campaign) || $campaign->created_by_user_id === $user->id || $user->can('moderation.manage')));
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign) && in_array($campaign->status, [CampaignStatus::DRAFT, CampaignStatus::REJECTED], true);
    }

    public function submit(User $user, Campaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    public function review(User $user, Campaign $campaign): bool
    {
        return $user->can('moderation.manage');
    }

    public function owns(User $user, Campaign $campaign): bool
    {
        return $campaign->owner_user_id === $user->id || ($campaign->ownerOrganization && $campaign->ownerOrganization->memberships()->where('user_id', $user->id)->where('status', OrganizationMembershipStatus::ACTIVE->value)->whereIn('membership_role', [OrganizationMembershipRole::OWNER->value, OrganizationMembershipRole::ADMIN->value])->exists());
    }

    private function isPublic(Campaign $campaign): bool
    {
        return $campaign->status === CampaignStatus::PUBLISHED && in_array($campaign->visibility, [CampaignVisibility::PUBLIC, CampaignVisibility::UNLISTED], true);
    }
}
