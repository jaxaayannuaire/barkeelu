<?php

namespace App\Services\Beneficiaries;

use App\Enums\BeneficiaryRepresentativeStatus;
use App\Models\Beneficiary;
use App\Models\BeneficiaryRepresentative;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AddBeneficiaryRepresentative
{
    public function add(Beneficiary $beneficiary, User $representative, User $creator, CarbonInterface $validFrom, ?CarbonInterface $validUntil = null): BeneficiaryRepresentative
    {
        return DB::transaction(fn (): BeneficiaryRepresentative => BeneficiaryRepresentative::query()->create([
            'beneficiary_id' => $beneficiary->id,
            'representative_user_id' => $representative->id,
            'status' => BeneficiaryRepresentativeStatus::ACTIVE,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'created_by_user_id' => $creator->id,
        ]));
    }
}
