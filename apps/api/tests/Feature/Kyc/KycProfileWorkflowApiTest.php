<?php

namespace Tests\Feature\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KycProfileWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_requires_sanctum_and_uses_public_id_binding(): void
    {
        [$owner, $profile] = $this->profileWithDocument();

        $this->postJson("/api/v1/kyc/profiles/{$profile->public_id}/submit")->assertUnauthorized();
        $this->as($owner)->postJson("/api/v1/kyc/profiles/{$profile->id}/submit")->assertNotFound();
    }

    public function test_submit_returns_resource_then_second_call_returns_conflict(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}/submit";

        $this->as($owner)->postJson($uri)
            ->assertOk()
            ->assertJsonPath('data.public_id', $profile->public_id)
            ->assertJsonPath('data.status', KycStatus::SUBMITTED->value)
            ->assertJsonMissingPath('data.id');

        $this->as($owner)->postJson($uri)
            ->assertStatus(409)
            ->assertJson(['message' => 'Action KYC impossible.', 'code' => 'KYC_TRANSITION_INVALID']);
    }

    public function test_start_review_requires_compliance_and_double_start_conflicts(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}";
        $this->as($owner)->postJson("{$uri}/submit")->assertOk();

        $this->as($owner)->postJson("{$uri}/review/start")
            ->assertForbidden()
            ->assertJsonPath('code', 'KYC_ACTOR_UNAUTHORIZED');

        $this->as($compliance)->postJson("{$uri}/review/start")
            ->assertOk()
            ->assertJsonPath('data.status', KycStatus::UNDER_REVIEW->value);

        $this->as($compliance)->postJson("{$uri}/review/start")
            ->assertStatus(409)
            ->assertJsonPath('code', 'KYC_TRANSITION_INVALID');
    }

    public function test_verify_requires_current_reviewer_and_maps_document_errors(): void
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::UPLOADED);
        $compliance = $this->complianceUser();
        $other = $this->complianceUser();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}";
        $this->as($owner)->postJson("{$uri}/submit")->assertOk();
        $this->as($compliance)->postJson("{$uri}/review/start")->assertOk();

        $this->as($other)->postJson("{$uri}/verify")
            ->assertForbidden()
            ->assertJsonPath('code', 'KYC_REVIEWER_INVALID');
        $this->as($compliance)->postJson("{$uri}/verify")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'KYC_DOCUMENT_NOT_ACCEPTED');
        $this->assertSame(KycStatus::UNDER_REVIEW, $profile->fresh()->status);
    }

    public function test_reject_validation_and_success_are_stable(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}";
        $this->as($owner)->postJson("{$uri}/submit")->assertOk();
        $this->as($compliance)->postJson("{$uri}/review/start")->assertOk();

        $this->as($compliance)->postJson("{$uri}/reject", ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->as($compliance)->postJson("{$uri}/reject", ['reason' => 'Document incomplet'])
            ->assertOk()
            ->assertJsonPath('data.status', KycStatus::REJECTED->value)
            ->assertJsonPath('data.rejection_reason', 'Document incomplet');
    }

    public function test_reopen_and_risk_endpoints_use_policy_and_workflow_mapping(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}";
        $this->as($owner)->postJson("{$uri}/submit")->assertOk();
        $this->as($compliance)->postJson("{$uri}/review/start")->assertOk();
        $this->as($compliance)->postJson("{$uri}/reject", ['reason' => 'Correction'])->assertOk();

        $this->as($owner)->postJson("{$uri}/reopen")->assertOk()->assertJsonPath('data.status', KycStatus::DRAFT->value);
        $this->as($compliance)->postJson("{$uri}/risk", [
            'risk_level' => KycRiskLevel::HIGH->value,
            'reason_code' => 'MANUAL_REVIEW',
        ])->assertOk()->assertJsonPath('data.risk_level', KycRiskLevel::HIGH->value);

        $this->as($compliance)->postJson("{$uri}/risk", [
            'risk_level' => KycRiskLevel::HIGH->value,
            'reason_code' => 'MANUAL_REVIEW',
        ])->assertStatus(409)->assertJsonPath('code', 'KYC_RISK_UNCHANGED');
    }

    public function test_expire_requires_reason_and_never_accepts_system_actor_input(): void
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
        $compliance = $this->complianceUser();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}";
        $this->as($owner)->postJson("{$uri}/submit")->assertOk();
        $this->as($compliance)->postJson("{$uri}/review/start")->assertOk();
        $this->as($compliance)->postJson("{$uri}/verify")->assertOk();

        $this->as($compliance)->postJson("{$uri}/expire", ['actor_type' => 'SYSTEM'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->as($compliance)->postJson("{$uri}/expire", ['reason' => 'Document expiré'])
            ->assertOk()
            ->assertJsonPath('data.status', KycStatus::EXPIRED->value);
    }

    public function test_suspend_requires_compliance_and_reopen_suspended_requires_compliance(): void
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
        $compliance = $this->complianceUser();
        $uri = "/api/v1/kyc/profiles/{$profile->public_id}";
        $this->as($owner)->postJson("{$uri}/submit")->assertOk();
        $this->as($compliance)->postJson("{$uri}/review/start")->assertOk();
        $this->as($compliance)->postJson("{$uri}/verify")->assertOk();

        $this->as($owner)->postJson("{$uri}/suspend", ['reason' => 'Risque'])
            ->assertForbidden()
            ->assertJsonPath('code', 'KYC_ACTOR_UNAUTHORIZED');
        $this->as($compliance)->postJson("{$uri}/suspend", ['reason' => 'Risque'])
            ->assertOk()
            ->assertJsonPath('data.status', KycStatus::SUSPENDED->value);
        $this->as($owner)->postJson("{$uri}/reopen", ['reason' => 'Levée'])
            ->assertForbidden();
        $this->as($compliance)->postJson("{$uri}/reopen", ['reason' => 'Levée'])
            ->assertOk()
            ->assertJsonPath('data.status', KycStatus::UNDER_REVIEW->value);
    }

    public function test_workflow_errors_return_json_without_internal_data(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $response = $this->as($owner)->postJson("/api/v1/kyc/profiles/{$profile->public_id}/submit");

        $response->assertHeader('Content-Type', 'application/json')
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('sql')
            ->assertJsonMissingPath('data.id');
    }

    private function as(User $user): self
    {
        return $this->actingAs($user);
    }

    private function profileWithDocument(KycDocumentStatus $status = KycDocumentStatus::UPLOADED): array
    {
        $owner = User::factory()->create();
        $profile = app(CreateKycProfile::class)->create($owner, $owner);
        KycDocument::query()->create([
            'public_id' => (string) Str::uuid(),
            'kyc_profile_id' => $profile->id,
            'type' => KycDocumentType::IDENTITY_DOCUMENT,
            'status' => $status,
            'storage_disk' => 'kyc_private',
            'object_key' => 'tests/'.Str::uuid().'.pdf',
            'sha256' => str_repeat('a', 64),
            'mime_type' => 'application/pdf',
            'size_bytes' => 1,
            'uploaded_by_user_id' => $owner->id,
        ]);

        return [$owner, $profile];
    }

    private function complianceUser(): User
    {
        Permission::findOrCreate('compliance.manage', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('compliance.manage');

        return $user;
    }
}
