<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BeneficiaryRepresentativeStatus;
use App\Enums\BeneficiaryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBeneficiaryRepresentativeRequest;
use App\Http\Requests\StoreBeneficiaryRequest;
use App\Http\Requests\UpdateBeneficiaryRequest;
use App\Http\Resources\BeneficiaryRepresentativeResource;
use App\Http\Resources\BeneficiaryResource;
use App\Models\Beneficiary;
use App\Models\Organization;
use App\Models\User;
use App\Services\Beneficiaries\AddBeneficiaryRepresentative;
use App\Services\Beneficiaries\CreateBeneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BeneficiaryController extends Controller
{
    public function index(Request $request)
    {
        $query = Beneficiary::query()->latest('id');
        if (! $request->user()->can('compliance.manage')) {
            $query->where(function ($beneficiaries) use ($request): void {
                $beneficiaries->where('created_by_user_id', $request->user()->id)->orWhereHas('representatives', fn ($representatives) => $representatives->where('representative_user_id', $request->user()->id)->where('status', BeneficiaryRepresentativeStatus::ACTIVE->value)->where('valid_from', '<=', now())->where(fn ($validity) => $validity->whereNull('valid_until')->orWhere('valid_until', '>', now())));
            });
        }

        return BeneficiaryResource::collection($query->paginate());
    }

    public function store(StoreBeneficiaryRequest $request, CreateBeneficiary $service): JsonResponse
    {
        $organization = $request->validated('linked_organization_public_id') ? Organization::query()->where('public_id', $request->validated('linked_organization_public_id'))->firstOrFail() : null;
        $linkedUser = $request->validated('linked_user_id') ? User::query()->findOrFail($request->validated('linked_user_id')) : null;
        $beneficiary = $service->create($request->user(), $request->validated('display_name'), BeneficiaryType::from($request->validated('type')), $linkedUser, $organization);

        return (new BeneficiaryResource($beneficiary))->response()->setStatusCode(201);
    }

    public function show(Beneficiary $beneficiary): BeneficiaryResource
    {
        Gate::authorize('view', $beneficiary);

        return new BeneficiaryResource($beneficiary);
    }

    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): BeneficiaryResource
    {
        Gate::authorize('update', $beneficiary);
        $beneficiary->update($request->validated());

        return new BeneficiaryResource($beneficiary->refresh());
    }

    public function representatives(Beneficiary $beneficiary)
    {
        Gate::authorize('view', $beneficiary);

        return BeneficiaryRepresentativeResource::collection($beneficiary->representatives()->latest('id')->paginate());
    }

    public function storeRepresentative(StoreBeneficiaryRepresentativeRequest $request, Beneficiary $beneficiary, AddBeneficiaryRepresentative $service): JsonResponse
    {
        Gate::authorize('manageRepresentatives', $beneficiary);
        $representative = $service->add($beneficiary, User::query()->findOrFail($request->validated('representative_user_id')), $request->user(), now()->parse($request->validated('valid_from')), $request->validated('valid_until') ? now()->parse($request->validated('valid_until')) : null);

        return (new BeneficiaryRepresentativeResource($representative))->response()->setStatusCode(201);
    }
}
