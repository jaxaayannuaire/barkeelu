<?php

namespace App\Services\Beneficiaries;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Models\Beneficiary;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateBeneficiary
{
    public function create(User $creator, string $displayName, BeneficiaryType $type, ?User $linkedUser = null, ?Organization $linkedOrganization = null): Beneficiary
    {
        return DB::transaction(function () use ($creator, $displayName, $type, $linkedUser, $linkedOrganization): Beneficiary {
            return Beneficiary::query()->create([
                'public_id' => (string) Str::uuid(),
                'display_name' => $displayName,
                'type' => $type,
                'status' => BeneficiaryStatus::ACTIVE,
                'linked_user_id' => $linkedUser?->id,
                'linked_organization_id' => $linkedOrganization?->id,
                'created_by_user_id' => $creator->id,
            ]);
        });
    }
}
