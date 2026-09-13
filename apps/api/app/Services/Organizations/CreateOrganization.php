<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationMembershipRole;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganization
{
    public function create(User $creator, string $name, OrganizationType $type): Organization
    {
        return DB::transaction(function () use ($creator, $name, $type): Organization {
            $organization = new Organization;
            $organization->forceFill([
                'public_id' => (string) Str::uuid(),
                'name' => $name,
                'slug' => $this->nextSlug($name),
                'type' => $type,
                'status' => OrganizationStatus::ACTIVE,
                'created_by_user_id' => $creator->id,
            ]);
            $organization->save();

            OrganizationMember::query()->create([
                'organization_id' => $organization->id,
                'user_id' => $creator->id,
                'membership_role' => OrganizationMembershipRole::OWNER,
                'status' => OrganizationMembershipStatus::ACTIVE,
                'joined_at' => now(),
            ]);

            return $organization;
        });
    }

    private function nextSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'organization';
        $slug = $baseSlug;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
