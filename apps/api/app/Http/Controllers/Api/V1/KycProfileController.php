<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KycDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKycDocumentRequest;
use App\Http\Requests\StoreKycProfileRequest;
use App\Http\Resources\KycDocumentResource;
use App\Http\Resources\KycProfileResource;
use App\Models\Beneficiary;
use App\Models\KycProfile;
use App\Models\Organization;
use App\Models\User;
use App\Policies\KycProfilePolicy;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\UploadKycDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class KycProfileController extends Controller
{
    public function store(StoreKycProfileRequest $request, CreateKycProfile $service): JsonResponse
    {
        $subject = $this->resolveSubject($request->validated());
        abort_unless(app(KycProfilePolicy::class)->create($request->user(), $subject), 403);
        $profile = $service->create($subject);

        return (new KycProfileResource($profile))->response()->setStatusCode(201);
    }

    public function show(KycProfile $kycProfile): KycProfileResource
    {
        Gate::authorize('view', $kycProfile);

        return new KycProfileResource($kycProfile);
    }

    public function storeDocument(StoreKycDocumentRequest $request, KycProfile $kycProfile, UploadKycDocument $service): JsonResponse
    {
        Gate::authorize('uploadDocument', $kycProfile);
        $document = $service->upload($kycProfile, $request->user(), $request->file('file'), KycDocumentType::from($request->validated('type')), $request->validated('issued_at'), $request->validated('expires_at'));

        return (new KycDocumentResource($document))->response()->setStatusCode(201);
    }

    /** @param array<string, mixed> $attributes */
    private function resolveSubject(array $attributes): User|Organization|Beneficiary
    {
        if (isset($attributes['user_id'])) {
            return User::query()->findOrFail($attributes['user_id']);
        }
        if (isset($attributes['organization_public_id'])) {
            return Organization::query()->where('public_id', $attributes['organization_public_id'])->firstOrFail();
        }

        return Beneficiary::query()->where('public_id', $attributes['beneficiary_public_id'])->firstOrFail();
    }
}
