<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\KycDocumentType;
use App\Enums\KycRiskLevel;
use App\Exceptions\KycWorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\KycReasonRequest;
use App\Http\Requests\KycReopenRequest;
use App\Http\Requests\KycRiskRequest;
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
use App\Services\Kyc\KycProfileWorkflow;
use App\Services\Kyc\UploadKycDocument;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KycProfileController extends Controller
{
    public function store(StoreKycProfileRequest $request, CreateKycProfile $service): JsonResponse
    {
        $subject = $this->resolveSubject($request->validated());
        abort_unless(app(KycProfilePolicy::class)->create($request->user(), $subject), 403);
        $profile = $service->create($subject, $request->user());

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

    public function submit(Request $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('submit', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->submit($kycProfile, $request->user()));
    }

    public function startReview(Request $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('startReview', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->startReview($kycProfile, $request->user()));
    }

    public function verify(Request $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('verify', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->verify($kycProfile, $request->user()));
    }

    public function reject(KycReasonRequest $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('reject', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->reject($kycProfile, $request->user(), $request->validated('reason')));
    }

    public function suspend(KycReasonRequest $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('suspend', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->suspend($kycProfile, $request->user(), $request->validated('reason')));
    }

    public function expire(KycReasonRequest $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('expire', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->expireByCompliance($kycProfile, $request->user(), $request->validated('reason')));
    }

    public function reopen(KycReopenRequest $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('reopen', $kycProfile)) {
            return $response;
        }
        $reason = $request->validated('reason');

        return $this->workflowResponse(function () use ($kycProfile, $workflow, $request, $reason): KycProfile {
            if ($kycProfile->status->value === 'SUSPENDED') {
                return $workflow->reopenSuspended($kycProfile, $request->user(), $reason ?? '');
            }

            return $workflow->reopenToDraft($kycProfile, $request->user(), $reason);
        });
    }

    public function changeRisk(KycRiskRequest $request, KycProfile $kycProfile, KycProfileWorkflow $workflow): JsonResponse
    {
        if ($response = $this->authorizeWorkflow('changeRisk', $kycProfile)) {
            return $response;
        }

        return $this->workflowResponse(fn () => $workflow->changeRisk($kycProfile, $request->user(), KycRiskLevel::from($request->validated('risk_level')), $request->validated('reason_code'), $request->validated('reason_text')));
    }

    /** @param Closure(): KycProfile $operation */
    private function workflowResponse(Closure $operation): JsonResponse
    {
        try {
            return (new KycProfileResource($operation()))->response()->setStatusCode(200);
        } catch (KycWorkflowException $exception) {
            return response()->json([
                'message' => 'Action KYC impossible.',
                'code' => $exception->errorCode()->value,
            ], $this->workflowStatus($exception));
        }
    }

    private function workflowStatus(KycWorkflowException $exception): int
    {
        return match ($exception->errorCode()->value) {
            'KYC_ACTOR_UNAUTHORIZED', 'KYC_SELF_REVIEW', 'KYC_REVIEWER_IS_SUBMITTER', 'KYC_REVIEWER_INVALID' => 403,
            'KYC_TRANSITION_INVALID', 'KYC_RISK_UNCHANGED' => 409,
            default => 422,
        };
    }

    private function authorizeWorkflow(string $ability, KycProfile $profile): ?JsonResponse
    {
        try {
            Gate::authorize($ability, $profile);
        } catch (AuthorizationException) {
            return response()->json([
                'message' => 'Action KYC interdite.',
                'code' => 'KYC_ACTOR_UNAUTHORIZED',
            ], 403);
        }

        return null;
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
