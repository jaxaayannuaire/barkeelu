<?php

namespace App\Services\Kyc;

use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEntityType;
use App\Enums\KycReviewEventType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\KycProfile;
use App\Models\KycReviewEvent;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RecordKycReviewEvent
{
    public function record(
        KycProfile $profile,
        KycReviewEventType $eventType,
        KycReviewActorType $actorType,
        ?User $actor = null,
        ?KycStatus $fromStatus = null,
        ?KycStatus $toStatus = null,
        ?KycRiskLevel $riskBefore = null,
        ?KycRiskLevel $riskAfter = null,
        ?string $reasonCode = null,
        ?string $reasonText = null,
    ): KycReviewEvent {
        if ($actorType === KycReviewActorType::HUMAN && $actor === null) {
            throw new InvalidArgumentException('Un événement HUMAN exige un acteur.');
        }
        if ($actorType === KycReviewActorType::SYSTEM && $actor !== null) {
            throw new InvalidArgumentException('Un événement SYSTEM ne peut pas avoir d’acteur utilisateur.');
        }

        return KycReviewEvent::query()->create([
            'public_id' => (string) Str::uuid(),
            'entity_type' => KycReviewEntityType::PROFILE,
            'kyc_profile_id' => $profile->id,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'risk_level_before' => $riskBefore,
            'risk_level_after' => $riskAfter,
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
            'actor_type' => $actorType,
            'actor_user_id' => $actor?->id,
        ]);
    }
}
