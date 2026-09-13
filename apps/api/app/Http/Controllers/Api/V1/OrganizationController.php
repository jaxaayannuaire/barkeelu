<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\Organizations\CreateOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::query()->latest('id');

        if (! $request->user()->hasPermissionTo('organizations.manage_all', 'web')) {
            $query->whereHas('memberships', fn ($memberships) => $memberships
                ->where('user_id', $request->user()->id)
                ->where('status', OrganizationMembershipStatus::ACTIVE->value));
        }

        return OrganizationResource::collection($query->paginate());
    }

    public function store(StoreOrganizationRequest $request, CreateOrganization $service): JsonResponse
    {
        $organization = $service->create(
            $request->user(),
            $request->validated('name'),
            OrganizationType::from($request->validated('type')),
        );

        return (new OrganizationResource($organization))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Organization $organization): OrganizationResource
    {
        Gate::authorize('view', $organization);

        return new OrganizationResource($organization);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        Gate::authorize('update', $organization);

        $organization->update($request->validated());

        return new OrganizationResource($organization->refresh());
    }
}
