<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CampaignStatus;
use App\Enums\OrganizationMembershipRole;
use App\Enums\OrganizationMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewCampaignRequest;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\ManageCampaignResource;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Organization;
use App\Services\Campaigns\CreateCampaign;
use App\Services\Campaigns\TransitionCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CampaignController extends Controller
{
    public function index()
    {
        return CampaignResource::collection(Campaign::query()->publiclyListed()->latest('id')->paginate());
    }

    public function publicShow(string $slug): CampaignResource
    {
        $campaign = Campaign::query()->where('slug', $slug)->firstOrFail();
        abort_unless(Gate::allows('view', $campaign), 404);

        return new CampaignResource($campaign);
    }

    public function mine(Request $request)
    {
        $user = $request->user();

        return ManageCampaignResource::collection(Campaign::query()->where(fn ($campaigns) => $campaigns->where('owner_user_id', $user->id)->orWhereHas('ownerOrganization.memberships', fn ($memberships) => $memberships->where('user_id', $user->id)->where('status', OrganizationMembershipStatus::ACTIVE->value)->whereIn('membership_role', [OrganizationMembershipRole::OWNER->value, OrganizationMembershipRole::ADMIN->value])))->latest('id')->paginate());
    }

    public function store(StoreCampaignRequest $request, CreateCampaign $service): JsonResponse
    {
        $beneficiary = Beneficiary::query()->where('public_id', $request->validated('beneficiary_public_id'))->firstOrFail();
        Gate::authorize('view', $beneficiary);
        $organization = $this->organizationOwner($request);
        $campaign = $service->create($request->user(), $beneficiary, $request->validated(), $organization);

        return (new ManageCampaignResource($campaign))->response()->setStatusCode(201);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): ManageCampaignResource
    {
        Gate::authorize('update', $campaign);
        $campaign->update($request->validated());

        return new ManageCampaignResource($campaign->refresh());
    }

    public function submit(Campaign $campaign, TransitionCampaign $service): ManageCampaignResource
    {
        Gate::authorize('submit', $campaign);

        return new ManageCampaignResource($service->transition($campaign, CampaignStatus::SUBMITTED));
    }

    public function review(ReviewCampaignRequest $request, Campaign $campaign, TransitionCampaign $service): ManageCampaignResource
    {
        Gate::authorize('review', $campaign);
        $target = ['START_REVIEW' => CampaignStatus::UNDER_REVIEW, 'PUBLISH' => CampaignStatus::PUBLISHED, 'REJECT' => CampaignStatus::REJECTED][$request->validated('action')];

        return new ManageCampaignResource($service->transition($campaign, $target));
    }

    private function organizationOwner(StoreCampaignRequest $request): ?Organization
    {
        if (! $request->validated('owner_organization_public_id')) {
            return null;
        }
        $organization = Organization::query()->where('public_id', $request->validated('owner_organization_public_id'))->firstOrFail();
        abort_unless($organization->memberships()->where('user_id', $request->user()->id)->where('status', OrganizationMembershipStatus::ACTIVE->value)->whereIn('membership_role', [OrganizationMembershipRole::OWNER->value, OrganizationMembershipRole::ADMIN->value])->exists(), 403);

        return $organization;
    }
}
