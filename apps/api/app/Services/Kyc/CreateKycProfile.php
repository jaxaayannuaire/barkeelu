<?php

namespace App\Services\Kyc;

use App\Enums\KycReviewActorType;
use App\Enums\KycReviewEventType;
use App\Enums\KycRiskLevel;
use App\Enums\KycStatus;
use App\Models\Beneficiary;
use App\Models\KycProfile;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateKycProfile
{
    public function __construct(private readonly RecordKycReviewEvent $events) {}

    public function create(User|Organization|Beneficiary $subject, User $actor): KycProfile
    {
        return DB::transaction(function () use ($subject, $actor): KycProfile {
            $attributes = [
                'public_id' => (string) Str::uuid(),
                'status' => KycStatus::DRAFT,
                'risk_level' => KycRiskLevel::UNKNOWN,
            ];

            if ($subject instanceof User) {
                $attributes['user_id'] = $subject->id;
            } elseif ($subject instanceof Organization) {
                $attributes['organization_id'] = $subject->id;
            } else {
                $attributes['beneficiary_id'] = $subject->id;
            }

            $profile = KycProfile::query()->create($attributes);

            $this->events->record(
                $profile,
                KycReviewEventType::PROFILE_CREATED,
                KycReviewActorType::HUMAN,
                $actor,
                toStatus: KycStatus::DRAFT,
                riskAfter: KycRiskLevel::UNKNOWN,
            );

            return $profile;
        });
    }
}
