<?php

namespace Tests\Feature\Kyc;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentType;
use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEventType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Exceptions\KycWorkflowException;
use App\Models\Beneficiary;
use App\Models\KycDocument;
use App\Models\KycProfile;
use App\Models\User;
use App\Services\Kyc\CreateKycProfile;
use App\Services\Kyc\KycProfileWorkflow;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class KycProfileWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    public function test_draft_submission_and_review_start_are_atomic_and_audited(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $submitted = $workflow->submit($profile, $owner);
        $this->assertSame(KycStatus::SUBMITTED, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertSame($owner->id, $submitted->submitted_by_user_id);
        $this->assertEvent($submitted, KycReviewEventType::SUBMITTED, KycStatus::DRAFT, KycStatus::SUBMITTED, $owner);

        $reviewing = $workflow->startReview($profile, $compliance);
        $this->assertSame(KycStatus::UNDER_REVIEW, $reviewing->status);
        $this->assertSame($compliance->id, $reviewing->current_reviewer_user_id);
        $this->assertNotNull($reviewing->review_started_at);
        $this->assertEvent($reviewing, KycReviewEventType::REVIEW_STARTED, KycStatus::SUBMITTED, KycStatus::UNDER_REVIEW, $compliance);
    }

    public function test_verify_requires_current_reviewer_and_accepted_documents(): void
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
        $compliance = $this->complianceUser();
        $otherCompliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);

        $this->assertWorkflowError(fn () => $workflow->verify($profile, $otherCompliance), 'KYC_REVIEWER_INVALID');
        $this->assertWorkflowError(fn () => $workflow->reject($profile, $otherCompliance, 'Motif'), 'KYC_REVIEWER_INVALID');
        $this->assertSame(KycStatus::UNDER_REVIEW, $profile->fresh()->status);
    }

    public function test_verify_rejects_uploaded_document_until_document_workflow_accepts_it(): void
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::UPLOADED);
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);

        $this->assertWorkflowError(fn () => $workflow->verify($profile, $compliance), 'KYC_DOCUMENT_NOT_ACCEPTED');
    }

    public function test_submit_and_verify_reject_rejected_expired_or_effectively_expired_documents(): void
    {
        foreach ([KycDocumentStatus::REJECTED, KycDocumentStatus::EXPIRED] as $status) {
            [$owner, $profile] = $this->profileWithDocument($status);
            $this->assertWorkflowError(fn () => app(KycProfileWorkflow::class)->submit($profile, $owner), 'KYC_DOCUMENT_REQUIREMENT_MISSING');
        }

        [$owner, $profile] = $this->profileWithDocument();
        $profile->documents()->firstOrFail()->update(['expires_at' => now()->subDay()->toDateString()]);
        $this->assertWorkflowError(fn () => app(KycProfileWorkflow::class)->submit($profile, $owner), 'KYC_DOCUMENT_EXPIRED');

        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);
        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);
        $profile->documents()->firstOrFail()->update(['expires_at' => now()->subDay()->toDateString()]);
        $this->assertWorkflowError(fn () => $workflow->verify($profile, $compliance), 'KYC_DOCUMENT_EXPIRED');
    }

    public function test_accepted_document_without_expiry_or_with_future_expiry_verifies(): void
    {
        foreach ([null, now()->addDay()->toDateString()] as $expiresAt) {
            [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
            $profile->documents()->firstOrFail()->update(['expires_at' => $expiresAt]);
            $compliance = $this->complianceUser();
            $workflow = app(KycProfileWorkflow::class);
            $workflow->submit($profile, $owner);
            $workflow->startReview($profile, $compliance);
            $this->assertSame(KycStatus::VERIFIED, $workflow->verify($profile, $compliance)->status);
        }
    }

    public function test_full_review_can_verify_and_clears_current_reviewer(): void
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);
        $verified = $workflow->verify($profile, $compliance);

        $this->assertSame(KycStatus::VERIFIED, $verified->status);
        $this->assertNotNull($verified->reviewed_at);
        $this->assertNotNull($verified->verified_at);
        $this->assertSame($compliance->id, $verified->reviewed_by_user_id);
        $this->assertNull($verified->current_reviewer_user_id);
        $this->assertEvent($verified, KycReviewEventType::VERIFIED, KycStatus::UNDER_REVIEW, KycStatus::VERIFIED, $compliance);
    }

    public function test_reject_requires_reason_and_reopen_clears_current_cycle(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);
        try {
            $workflow->reject($profile, $compliance, '   ');
            $this->fail('Un motif vide doit être refusé.');
        } catch (DomainException $exception) {
            $this->assertSame('KYC_REASON_REQUIRED', $exception instanceof KycWorkflowException ? $exception->errorCode()->value : '');
        }

        $rejected = $workflow->reject($profile, $compliance, 'Document illisible');
        $this->assertSame(KycStatus::REJECTED, $rejected->status);
        $this->assertSame('Document illisible', $rejected->rejection_reason);
        $this->assertEvent($rejected, KycReviewEventType::REJECTED, KycStatus::UNDER_REVIEW, KycStatus::REJECTED, $compliance);

        $draft = $workflow->reopenToDraft($profile, $owner);
        $this->assertSame(KycStatus::DRAFT, $draft->status);
        $this->assertNull($draft->submitted_at);
        $this->assertNull($draft->submitted_by_user_id);
        $this->assertNull($draft->reviewed_at);
        $this->assertNull($draft->rejection_reason);
        $this->assertEvent($draft, KycReviewEventType::REOPENED, KycStatus::REJECTED, KycStatus::DRAFT, $owner);
    }

    public function test_suspend_expire_and_reopen_suspended_are_audited(): void
    {
        [$owner, $profile] = $this->verifiedProfile();
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $suspended = $workflow->suspend($profile, $compliance, 'Risk compliance');
        $this->assertSame(KycStatus::SUSPENDED, $suspended->status);
        $this->assertNotNull($suspended->suspended_at);
        $this->assertSame('Risk compliance', $suspended->suspension_reason);
        $this->assertEvent($suspended, KycReviewEventType::SUSPENDED, KycStatus::VERIFIED, KycStatus::SUSPENDED, $compliance);

        $underReview = $workflow->reopenSuspended($profile, $compliance, 'Nouvelle analyse');
        $this->assertSame(KycStatus::UNDER_REVIEW, $underReview->status);
        $this->assertSame($compliance->id, $underReview->current_reviewer_user_id);
        $this->assertNull($underReview->verified_at);
        $this->assertNull($underReview->suspension_reason);
        $this->assertEvent($underReview, KycReviewEventType::REOPENED, KycStatus::SUSPENDED, KycStatus::UNDER_REVIEW, $compliance);

        $workflow->verify($profile, $compliance);
        $expired = $workflow->expireBySystem($profile, 'DOCUMENT_EXPIRATION');
        $this->assertSame(KycStatus::EXPIRED, $expired->status);
        $this->assertNotNull($expired->verified_at);
        $this->assertEvent($expired, KycReviewEventType::EXPIRED, KycStatus::VERIFIED, KycStatus::EXPIRED, null);

        $draft = $workflow->reopenToDraft($profile, $owner);
        $this->assertSame(KycStatus::DRAFT, $draft->status);
        $this->assertEvent($draft, KycReviewEventType::REOPENED, KycStatus::EXPIRED, KycStatus::DRAFT, $owner);
    }

    public function test_human_expire_and_risk_change_require_compliance_and_reason(): void
    {
        [$owner, $profile] = $this->verifiedProfile();
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        try {
            $workflow->expireByCompliance($profile, $compliance, ' ');
            $this->fail('Expiration humaine sans motif doit être refusée.');
        } catch (DomainException $exception) {
            $this->assertSame('KYC_REASON_REQUIRED', $exception instanceof KycWorkflowException ? $exception->errorCode()->value : '');
        }

        $workflow->expireByCompliance($profile, $compliance, 'Document expiré');
        $profile->refresh();
        $this->assertSame(KycStatus::EXPIRED, $profile->status);

        $profile = app(KycProfileWorkflow::class)->reopenToDraft($profile, $owner);
        $this->assertSame(KycStatus::DRAFT, $profile->status);
        try {
            $workflow->changeRisk($profile, $compliance, KycRiskLevel::LOW, ' ');
            $this->fail('Changement risque sans code doit être refusé.');
        } catch (DomainException $exception) {
            $this->assertSame('KYC_REASON_REQUIRED', $exception instanceof KycWorkflowException ? $exception->errorCode()->value : '');
        }

        $changed = $workflow->changeRisk($profile, $compliance, KycRiskLevel::LOW, 'MANUAL_REVIEW');
        $this->assertSame(KycRiskLevel::LOW, $changed->risk_level);
        $event = $changed->reviewEvents()->latest('id')->firstOrFail();
        $this->assertSame(KycReviewEventType::RISK_CHANGED, $event->event_type);
        $this->assertNull($event->from_status);
        $this->assertNull($event->to_status);
        $this->assertSame(KycRiskLevel::UNKNOWN, $event->risk_level_before);
        $this->assertSame(KycRiskLevel::LOW, $event->risk_level_after);
        $this->assertSame('MANUAL_REVIEW', $event->reason_code);
    }

    public function test_reopen_preserves_risk_level_and_documents_and_events(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $workflow = app(KycProfileWorkflow::class);
        $compliance = $this->complianceUser();
        $workflow->changeRisk($profile, $compliance, KycRiskLevel::HIGH, 'HIGH_RISK');
        $documentId = $profile->documents()->value('id');
        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);
        $workflow->reject($profile, $compliance, 'Correction requise');
        $workflow->reopenToDraft($profile, $owner);

        $profile->refresh();
        $this->assertSame(KycRiskLevel::HIGH, $profile->risk_level);
        $this->assertDatabaseHas('kyc_documents', ['id' => $documentId]);
        $this->assertSame(1, $profile->reviewEvents()->where('event_type', KycReviewEventType::REJECTED->value)->count());
        $this->assertSame(1, $profile->reviewEvents()->where('event_type', KycReviewEventType::REOPENED->value)->count());
    }

    public function test_stale_risk_instance_uses_current_locked_risk(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $stale = KycProfile::query()->findOrFail($profile->id);
        $workflow = app(KycProfileWorkflow::class);

        $workflow->changeRisk($profile, $compliance, KycRiskLevel::LOW, 'FIRST_REVIEW');
        $changed = $workflow->changeRisk($stale, $compliance, KycRiskLevel::HIGH, 'SECOND_REVIEW');

        $this->assertSame(KycRiskLevel::HIGH, $changed->risk_level);
        $event = $changed->reviewEvents()->latest('id')->firstOrFail();
        $this->assertSame(KycRiskLevel::LOW, $event->risk_level_before);
        $this->assertSame(KycRiskLevel::HIGH, $event->risk_level_after);
    }

    public function test_invalid_transitions_and_self_review_are_refused(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $this->assertWorkflowError(fn () => $workflow->verify($profile, $compliance), 'KYC_TRANSITION_INVALID');
        $this->assertWorkflowError(fn () => $workflow->startReview($profile, $owner), 'KYC_ACTOR_UNAUTHORIZED');
        $this->assertWorkflowError(fn () => $workflow->submit($profile, User::factory()->create()), 'KYC_ACTOR_UNAUTHORIZED');

        $workflow->submit($profile, $owner);
        $owner->givePermissionTo('compliance.manage');
        $this->assertWorkflowError(fn () => $workflow->startReview($profile, $owner), 'KYC_REVIEWER_IS_SUBMITTER');
        $this->assertWorkflowError(fn () => $workflow->verify($profile, $compliance), 'KYC_TRANSITION_INVALID');
    }

    public function test_stale_review_start_cannot_replace_current_reviewer(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $reviewerA = $this->complianceUser();
        $reviewerB = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $stale = KycProfile::query()->findOrFail($profile->id);
        $workflow->startReview($profile, $reviewerA);

        $this->assertWorkflowError(fn () => $workflow->startReview($stale, $reviewerB), 'KYC_TRANSITION_INVALID');
        $profile->refresh();
        $this->assertSame($reviewerA->id, $profile->current_reviewer_user_id);
        $this->assertSame(1, $profile->reviewEvents()->where('event_type', KycReviewEventType::REVIEW_STARTED->value)->count());
    }

    public function test_compliance_guards_and_forbidden_transitions_are_enforced(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $compliance = $this->complianceUser();
        $regular = User::factory()->create();
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $this->assertWorkflowError(fn () => $workflow->verify($profile, $compliance), 'KYC_TRANSITION_INVALID');
        $this->assertWorkflowError(fn () => $workflow->verify($profile, $regular), 'KYC_ACTOR_UNAUTHORIZED');

        $workflow->startReview($profile, $compliance);
        $workflow->reject($profile, $compliance, 'Document illisible');
        $this->assertWorkflowError(fn () => $workflow->verify($profile, $compliance), 'KYC_TRANSITION_INVALID');

        [$verifiedOwner, $verified] = $this->verifiedProfile();
        $this->assertWorkflowError(fn () => $workflow->suspend($verified, $regular, 'Risque'), 'KYC_ACTOR_UNAUTHORIZED');
        $this->assertWorkflowError(fn () => $workflow->suspend($verified, $compliance, ' '), 'KYC_REASON_REQUIRED');
        $workflow->suspend($verified, $compliance, 'Risque');
        $this->assertWorkflowError(fn () => $workflow->reopenToDraft($verified, $verifiedOwner), 'KYC_TRANSITION_INVALID');
        $this->assertWorkflowError(fn () => $workflow->changeRisk($verified, $regular, KycRiskLevel::LOW, 'MANUAL'), 'KYC_ACTOR_UNAUTHORIZED');
        $this->assertWorkflowError(fn () => $workflow->changeRisk($verified, $compliance, KycRiskLevel::UNKNOWN, 'MANUAL'), 'KYC_RISK_UNCHANGED');
    }

    public function test_community_and_other_submission_requirements_are_explicitly_undefined(): void
    {
        foreach ([BeneficiaryType::COMMUNITY, BeneficiaryType::OTHER] as $type) {
            $owner = User::factory()->create();
            $beneficiary = Beneficiary::query()->create([
                'public_id' => (string) Str::uuid(),
                'display_name' => $type->value,
                'type' => $type,
                'status' => BeneficiaryStatus::ACTIVE,
                'created_by_user_id' => $owner->id,
            ]);
            $profile = app(CreateKycProfile::class)->create($beneficiary, $owner);

            $this->assertWorkflowError(fn () => app(KycProfileWorkflow::class)->submit($profile, $owner), 'SUBMISSION_REQUIREMENTS_UNDEFINED');
        }
    }

    public function test_submission_event_failure_rolls_back_profile_projection(): void
    {
        [$owner, $profile] = $this->profileWithDocument();

        DB::unprepared(<<<'SQL'
CREATE FUNCTION test_reject_submitted_event()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.event_type = 'SUBMITTED' THEN
        RAISE EXCEPTION 'test SUBMITTED failure';
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER test_reject_submitted_event
BEFORE INSERT ON kyc_review_events
FOR EACH ROW EXECUTE FUNCTION test_reject_submitted_event();
SQL);

        try {
            $exception = null;
            try {
                app(KycProfileWorkflow::class)->submit($profile, $owner);
            } catch (QueryException $caught) {
                $exception = $caught;
            }

            $this->assertNotNull($exception);
            $profile->refresh();
            $this->assertSame(KycStatus::DRAFT, $profile->status);
            $this->assertNull($profile->submitted_at);
            $this->assertSame(0, $profile->reviewEvents()->where('event_type', KycReviewEventType::SUBMITTED->value)->count());
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS test_reject_submitted_event ON kyc_review_events; DROP FUNCTION IF EXISTS test_reject_submitted_event();');
        }
    }

    public function test_stale_profile_instances_are_reloaded_before_transition(): void
    {
        [$owner, $profile] = $this->profileWithDocument();
        $stale = KycProfile::query()->findOrFail($profile->id);
        $workflow = app(KycProfileWorkflow::class);

        $workflow->submit($profile, $owner);
        $this->assertWorkflowError(fn () => $workflow->submit($stale, $owner), 'KYC_TRANSITION_INVALID');
        $this->assertSame(1, $profile->reviewEvents()->where('event_type', KycReviewEventType::SUBMITTED->value)->count());
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

    private function verifiedProfile(): array
    {
        [$owner, $profile] = $this->profileWithDocument(KycDocumentStatus::ACCEPTED);
        $compliance = $this->complianceUser();
        $workflow = app(KycProfileWorkflow::class);
        $workflow->submit($profile, $owner);
        $workflow->startReview($profile, $compliance);
        $workflow->verify($profile, $compliance);

        return [$owner, $profile];
    }

    private function complianceUser(): User
    {
        Permission::findOrCreate('compliance.manage', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('compliance.manage');

        return $user;
    }

    private function assertEvent(KycProfile $profile, KycReviewEventType $type, KycStatus $from, KycStatus $to, ?User $actor): void
    {
        $event = $profile->reviewEvents()->latest('id')->firstOrFail();
        $this->assertSame($type, $event->event_type);
        $this->assertSame($from->value, $event->from_status);
        $this->assertSame($to->value, $event->to_status);
        $this->assertSame($actor?->id, $event->actor_user_id);
        $this->assertSame($actor === null ? KycReviewActorType::SYSTEM : KycReviewActorType::HUMAN, $event->actor_type);
    }

    private function assertWorkflowError(callable $callback, string $code): void
    {
        try {
            $callback();
            $this->fail("Erreur métier {$code} attendue.");
        } catch (DomainException $exception) {
            if ($exception instanceof KycWorkflowException) {
                $this->assertSame($code, $exception->errorCode()->value);
            } else {
                $this->assertStringContainsString($code, $exception->getMessage());
            }
        }
    }
}
