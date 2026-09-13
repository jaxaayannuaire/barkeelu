<?php

namespace App\Services\Kyc;

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
    public function create(User|Organization|Beneficiary $subject): KycProfile
    {
        return DB::transaction(function () use ($subject): KycProfile {
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

            return KycProfile::query()->create($attributes);
        });
    }
}
