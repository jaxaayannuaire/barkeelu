<?php

namespace App\Services\Kyc;

use App\Enums\KycDocumentStatus;
use App\Enums\KycDocumentWorkflowErrorCode;
use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEventType;
use App\Exceptions\KycDocumentWorkflowException;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KycDocumentWorkflow
{
    public function __construct(private readonly RecordKycReviewEvent $events) {}

    public function accept(KycDocument $document, User $actor): KycDocument
    {
        return $this->humanTransition($document, $actor, KycDocumentStatus::ACCEPTED, KycReviewEventType::DOCUMENT_ACCEPTED);
    }

    public function reject(KycDocument $document, User $actor, string $reason, ?string $reasonCode = null): KycDocument
    {
        $reason = $this->requiredReason($reason);
        $reasonCode = $reasonCode === null ? null : $this->requiredCode($reasonCode);

        return $this->transition($document, function (KycDocument $locked) use ($actor, $reason, $reasonCode): KycDocument {
            $this->assertCompliance($actor);
            $this->assertNotUploader($locked, $actor);
            $from = $locked->status;
            if (! in_array($from, [KycDocumentStatus::UPLOADED, KycDocumentStatus::ACCEPTED], true)) {
                $this->invalidTransition($from, KycDocumentStatus::REJECTED);
            }
            $this->updateAndRecord($locked, $actor, KycDocumentStatus::REJECTED, KycReviewEventType::DOCUMENT_REJECTED, $reasonCode, $reason);

            return $locked;
        });
    }

    public function expireBySystem(KycDocument $document, string $reasonCode): KycDocument
    {
        $reasonCode = $this->requiredCode($reasonCode);

        return $this->transition($document, function (KycDocument $locked) use ($reasonCode): KycDocument {
            if ($locked->status !== KycDocumentStatus::ACCEPTED) {
                $this->invalidTransition($locked->status, KycDocumentStatus::EXPIRED);
            }
            if ($reasonCode === 'DOCUMENT_DATE_EXPIRED' && ($locked->expires_at === null || $locked->expires_at->isFuture())) {
                throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::EXPIRY_NOT_REACHED, 'Expiration document non atteinte.');
            }
            $locked->update(['status' => KycDocumentStatus::EXPIRED]);
            $this->events->recordDocument($locked, KycReviewEventType::DOCUMENT_EXPIRED, KycReviewActorType::SYSTEM, toStatus: KycDocumentStatus::EXPIRED, reasonCode: $reasonCode);

            return $locked;
        });
    }

    public function expireByCompliance(KycDocument $document, User $actor, string $reason): KycDocument
    {
        $reason = $this->requiredReason($reason);

        return $this->transition($document, function (KycDocument $locked) use ($actor, $reason): KycDocument {
            $this->assertCompliance($actor);
            $this->assertNotUploader($locked, $actor);
            if ($locked->status !== KycDocumentStatus::ACCEPTED) {
                $this->invalidTransition($locked->status, KycDocumentStatus::EXPIRED);
            }
            $this->updateAndRecord($locked, $actor, KycDocumentStatus::EXPIRED, KycReviewEventType::DOCUMENT_EXPIRED, null, $reason);

            return $locked;
        });
    }

    private function humanTransition(KycDocument $document, User $actor, KycDocumentStatus $to, KycReviewEventType $event): KycDocument
    {
        return $this->transition($document, function (KycDocument $locked) use ($actor, $to, $event): KycDocument {
            $this->assertCompliance($actor);
            $this->assertNotUploader($locked, $actor);
            if ($locked->status !== KycDocumentStatus::UPLOADED) {
                $this->invalidTransition($locked->status, $to);
            }
            $this->updateAndRecord($locked, $actor, $to, $event);

            return $locked;
        });
    }

    private function transition(KycDocument $document, callable $callback): KycDocument
    {
        return DB::transaction(function () use ($document, $callback): KycDocument {
            $locked = KycDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();

            return $callback($locked);
        });
    }

    private function updateAndRecord(KycDocument $document, User $actor, KycDocumentStatus $to, KycReviewEventType $event, ?string $reasonCode = null, ?string $reasonText = null): void
    {
        $from = $document->status;
        $document->update(['status' => $to, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()]);
        $this->events->recordDocument($document, $event, KycReviewActorType::HUMAN, $actor, $from, $to, $reasonCode, $reasonText);
    }

    private function assertCompliance(User $actor): void
    {
        if (! $actor->can('compliance.manage')) {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::FORBIDDEN_ACTOR, 'Permission compliance.manage requise.');
        }
    }

    private function assertNotUploader(KycDocument $document, User $actor): void
    {
        if ($document->uploaded_by_user_id === $actor->id) {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::SELF_REVIEW, 'Auto-revue documentaire interdite.');
        }
    }

    private function invalidTransition(KycDocumentStatus $from, KycDocumentStatus $to): never
    {
        throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::INVALID_TRANSITION, "Transition document KYC invalide : {$from->value} vers {$to->value}.");
    }

    private function requiredReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::REASON_REQUIRED, 'Motif obligatoire.');
        }
        if (mb_strlen($reason) > 2000) {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::REASON_TOO_LONG, 'Motif trop long.');
        }

        return $reason;
    }

    private function requiredCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::REASON_REQUIRED, 'Code motif obligatoire.');
        }
        if (mb_strlen($code) > 64) {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::REASON_TOO_LONG, 'Code motif trop long.');
        }
        if (! preg_match('/^[A-Z0-9_]+$/', $code)) {
            throw new KycDocumentWorkflowException(KycDocumentWorkflowErrorCode::REASON_CODE_INVALID, 'Code motif invalide.');
        }

        return $code;
    }
}
