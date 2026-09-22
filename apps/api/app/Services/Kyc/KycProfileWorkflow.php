<?php

namespace App\Services\Kyc;

use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEventType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Enums\KycWorkflowErrorCode;
use App\Exceptions\KycWorkflowException;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class KycProfileWorkflow
{
    public function __construct(
        private readonly RecordKycReviewEvent $events,
        private readonly KycDocumentRequirements $documents,
    ) {}

    public function submit(KycProfile $profile, User $actor): KycProfile
    {
        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor): KycProfile {
            $this->assertCanManage($locked, $actor);
            $this->assertStatus($locked, KycStatus::DRAFT);
            $this->documents->assertMinimum($locked);

            $now = now();
            $locked->update([
                'status' => KycStatus::SUBMITTED,
                'submitted_at' => $now,
                'submitted_by_user_id' => $actor->id,
                'review_started_at' => null,
                'current_reviewer_user_id' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'verified_at' => null,
                'expires_at' => null,
                'suspended_at' => null,
                'rejection_reason' => null,
                'suspension_reason' => null,
            ]);

            $this->events->record($locked, KycReviewEventType::SUBMITTED, KycReviewActorType::HUMAN, $actor, KycStatus::DRAFT, KycStatus::SUBMITTED);

            return $locked;
        });
    }

    public function startReview(KycProfile $profile, User $actor): KycProfile
    {
        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor): KycProfile {
            $this->assertCompliance($actor);
            $this->assertStatus($locked, KycStatus::SUBMITTED);
            $this->assertNotSelfReviewer($locked, $actor);

            $now = now();
            $locked->update([
                'status' => KycStatus::UNDER_REVIEW,
                'review_started_at' => $now,
                'current_reviewer_user_id' => $actor->id,
            ]);
            $this->events->record($locked, KycReviewEventType::REVIEW_STARTED, KycReviewActorType::HUMAN, $actor, KycStatus::SUBMITTED, KycStatus::UNDER_REVIEW);

            return $locked;
        });
    }

    public function verify(KycProfile $profile, User $actor): KycProfile
    {
        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor): KycProfile {
            $this->assertCompliance($actor);
            $this->assertStatus($locked, KycStatus::UNDER_REVIEW);
            $this->assertCurrentReviewer($locked, $actor);
            $this->documents->assertAccepted($locked);

            $now = now();
            $locked->update([
                'status' => KycStatus::VERIFIED,
                'reviewed_at' => $now,
                'reviewed_by_user_id' => $actor->id,
                'verified_at' => $now,
                'current_reviewer_user_id' => null,
                'rejection_reason' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            $this->events->record($locked, KycReviewEventType::VERIFIED, KycReviewActorType::HUMAN, $actor, KycStatus::UNDER_REVIEW, KycStatus::VERIFIED);

            return $locked;
        });
    }

    public function reject(KycProfile $profile, User $actor, string $reason): KycProfile
    {
        $reason = $this->requiredReason($reason);

        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor, $reason): KycProfile {
            $this->assertCompliance($actor);
            $this->assertStatus($locked, KycStatus::UNDER_REVIEW);
            $this->assertCurrentReviewer($locked, $actor);

            $now = now();
            $locked->update([
                'status' => KycStatus::REJECTED,
                'reviewed_at' => $now,
                'reviewed_by_user_id' => $actor->id,
                'current_reviewer_user_id' => null,
                'rejection_reason' => $reason,
                'verified_at' => null,
                'expires_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            $this->events->record($locked, KycReviewEventType::REJECTED, KycReviewActorType::HUMAN, $actor, KycStatus::UNDER_REVIEW, KycStatus::REJECTED, reasonText: $reason);

            return $locked;
        });
    }

    public function suspend(KycProfile $profile, User $actor, string $reason): KycProfile
    {
        $reason = $this->requiredReason($reason);

        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor, $reason): KycProfile {
            $this->assertCompliance($actor);
            $this->assertStatus($locked, KycStatus::VERIFIED);
            $this->assertNotDirectUserSubject($locked, $actor);

            $locked->update(['status' => KycStatus::SUSPENDED, 'suspended_at' => now(), 'suspension_reason' => $reason]);
            $this->events->record($locked, KycReviewEventType::SUSPENDED, KycReviewActorType::HUMAN, $actor, KycStatus::VERIFIED, KycStatus::SUSPENDED, reasonText: $reason);

            return $locked;
        });
    }

    public function expireBySystem(KycProfile $profile, string $reasonCode): KycProfile
    {
        $reasonCode = $this->requiredCode($reasonCode);

        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($reasonCode): KycProfile {
            $this->assertStatus($locked, KycStatus::VERIFIED);
            $locked->update(['status' => KycStatus::EXPIRED]);
            $this->events->record($locked, KycReviewEventType::EXPIRED, KycReviewActorType::SYSTEM, null, KycStatus::VERIFIED, KycStatus::EXPIRED, reasonCode: $reasonCode);

            return $locked;
        });
    }

    public function expireByCompliance(KycProfile $profile, User $actor, string $reason): KycProfile
    {
        $reason = $this->requiredReason($reason);

        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor, $reason): KycProfile {
            $this->assertCompliance($actor);
            $this->assertStatus($locked, KycStatus::VERIFIED);
            $this->assertNotDirectUserSubject($locked, $actor);
            $locked->update(['status' => KycStatus::EXPIRED]);
            $this->events->record($locked, KycReviewEventType::EXPIRED, KycReviewActorType::HUMAN, $actor, KycStatus::VERIFIED, KycStatus::EXPIRED, reasonText: $reason);

            return $locked;
        });
    }

    public function reopenToDraft(KycProfile $profile, User $actor, ?string $reason = null): KycProfile
    {
        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor, $reason): KycProfile {
            $this->assertCanManage($locked, $actor);
            $from = $locked->status;
            if (! in_array($from, [KycStatus::REJECTED, KycStatus::EXPIRED], true)) {
                $this->invalidTransition($from, KycStatus::DRAFT);
            }
            $reason = $this->optionalReasonForCompliance($actor, $reason);
            $locked->update([
                'status' => KycStatus::DRAFT,
                'submitted_at' => null,
                'submitted_by_user_id' => null,
                'review_started_at' => null,
                'current_reviewer_user_id' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'verified_at' => null,
                'expires_at' => null,
                'suspended_at' => null,
                'rejection_reason' => null,
                'suspension_reason' => null,
            ]);
            $this->events->record($locked, KycReviewEventType::REOPENED, KycReviewActorType::HUMAN, $actor, $from, KycStatus::DRAFT, reasonText: $reason);

            return $locked;
        });
    }

    public function reopenSuspended(KycProfile $profile, User $actor, string $reason): KycProfile
    {
        $reason = $this->requiredReason($reason);

        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor, $reason): KycProfile {
            $this->assertCompliance($actor);
            $this->assertStatus($locked, KycStatus::SUSPENDED);
            $this->assertNotSelfReviewer($locked, $actor);
            $locked->update([
                'status' => KycStatus::UNDER_REVIEW,
                'review_started_at' => now(),
                'current_reviewer_user_id' => $actor->id,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'verified_at' => null,
                'expires_at' => null,
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);
            $this->events->record($locked, KycReviewEventType::REOPENED, KycReviewActorType::HUMAN, $actor, KycStatus::SUSPENDED, KycStatus::UNDER_REVIEW, reasonText: $reason);

            return $locked;
        });
    }

    public function changeRisk(KycProfile $profile, User $actor, KycRiskLevel $risk, string $reasonCode, ?string $reasonText = null): KycProfile
    {
        $reasonCode = $this->requiredCode($reasonCode);

        return $this->withLockedProfile($profile, function (KycProfile $locked) use ($actor, $risk, $reasonCode, $reasonText): KycProfile {
            $this->assertCompliance($actor);
            $before = $locked->risk_level;
            if ($before === $risk) {
                throw new KycWorkflowException(KycWorkflowErrorCode::RISK_UNCHANGED, 'Niveau de risque inchangé.');
            }
            $locked->update(['risk_level' => $risk]);
            $this->events->record($locked, KycReviewEventType::RISK_CHANGED, KycReviewActorType::HUMAN, $actor, riskBefore: $before, riskAfter: $risk, reasonCode: $reasonCode, reasonText: $reasonText ? trim($reasonText) : null);

            return $locked;
        });
    }

    private function withLockedProfile(KycProfile $profile, callable $callback): KycProfile
    {
        return DB::transaction(function () use ($profile, $callback): KycProfile {
            $locked = KycProfile::query()->whereKey($profile->getKey())->lockForUpdate()->firstOrFail();

            return $callback($locked);
        });
    }

    private function assertStatus(KycProfile $profile, KycStatus $expected): void
    {
        if ($profile->status !== $expected) {
            $this->invalidTransition($profile->status, $expected);
        }
    }

    private function invalidTransition(KycStatus $from, KycStatus $to): never
    {
        throw new KycWorkflowException(KycWorkflowErrorCode::INVALID_TRANSITION, "Transition KYC invalide : {$from->value} vers {$to->value}.");
    }

    private function assertCompliance(User $actor): void
    {
        if (! $actor->can('compliance.manage')) {
            throw new KycWorkflowException(KycWorkflowErrorCode::FORBIDDEN_ACTOR, 'Permission compliance.manage requise.');
        }
    }

    private function assertCanManage(KycProfile $profile, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('view', $profile)) {
            throw new KycWorkflowException(KycWorkflowErrorCode::FORBIDDEN_ACTOR, 'Sujet KYC non gérable.');
        }
    }

    private function assertNotSelfReviewer(KycProfile $profile, User $actor): void
    {
        if ($profile->submitted_by_user_id === $actor->id) {
            throw new KycWorkflowException(KycWorkflowErrorCode::REVIEWER_IS_SUBMITTER, 'Le submitter ne peut pas reviewer son propre dossier.');
        }
        if ($profile->user_id !== null && $profile->user_id === $actor->id) {
            throw new KycWorkflowException(KycWorkflowErrorCode::SELF_REVIEW_FORBIDDEN, 'Self-review interdit pour ce profil.');
        }
    }

    private function assertNotDirectUserSubject(KycProfile $profile, User $actor): void
    {
        if ($profile->user_id !== null && $profile->user_id === $actor->id) {
            throw new KycWorkflowException(KycWorkflowErrorCode::SELF_REVIEW_FORBIDDEN, 'Action compliance interdite sur son propre profil.');
        }
    }

    private function assertCurrentReviewer(KycProfile $profile, User $actor): void
    {
        $this->assertNotSelfReviewer($profile, $actor);
        if ($profile->current_reviewer_user_id !== $actor->id) {
            throw new KycWorkflowException(KycWorkflowErrorCode::NOT_CURRENT_REVIEWER, 'Reviewer courant requis.');
        }
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new KycWorkflowException(KycWorkflowErrorCode::REASON_REQUIRED, 'Motif obligatoire.');
        }
        if (mb_strlen($reason) > 2000) {
            throw new KycWorkflowException(KycWorkflowErrorCode::REASON_TOO_LONG, 'Motif trop long.');
        }

        return $reason;
    }

    private function requiredCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            throw new KycWorkflowException(KycWorkflowErrorCode::REASON_REQUIRED, 'Code motif obligatoire.');
        }
        if (mb_strlen($code) > 64) {
            throw new KycWorkflowException(KycWorkflowErrorCode::REASON_TOO_LONG, 'Code motif trop long.');
        }
        if (! preg_match('/^[A-Z0-9_]+$/', $code)) {
            throw new KycWorkflowException(KycWorkflowErrorCode::REASON_CODE_INVALID, 'Code motif invalide.');
        }

        return $code;
    }

    private function optionalReasonForCompliance(User $actor, ?string $reason): ?string
    {
        if ($actor->can('compliance.manage')) {
            return $this->requiredReason($reason ?? '');
        }

        return $reason === null ? null : $this->requiredReason($reason);
    }
}
