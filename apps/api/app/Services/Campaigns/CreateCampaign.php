<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCampaign
{
    /** @param array<string, mixed> $attributes */
    public function create(User $creator, Beneficiary $beneficiary, array $attributes, ?Organization $organization = null): Campaign
    {
        return DB::transaction(function () use ($creator, $beneficiary, $attributes, $organization): Campaign {
            return Campaign::query()->create([
                'public_id' => (string) Str::uuid(), 'owner_user_id' => $organization ? null : $creator->id, 'owner_organization_id' => $organization?->id,
                'created_by_user_id' => $creator->id, 'beneficiary_id' => $beneficiary->id, 'title' => $attributes['title'], 'slug' => $this->nextSlug($attributes['title']),
                'description' => $attributes['description'], 'goal_amount' => $attributes['goal_amount'], 'currency' => 'XOF', 'visibility' => CampaignVisibility::from($attributes['visibility']),
                'status' => CampaignStatus::DRAFT, 'fundraising_status' => CampaignFundraisingStatus::NOT_STARTED, 'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE,
                'featured' => false, 'gross_collected_nominal' => 0, 'refunded_nominal' => 0, 'net_collected_nominal' => 0, 'available_for_payout' => 0,
                'reserved_for_payout' => 0, 'paid_out_amount' => 0, 'donation_count' => 0, 'distinct_donor_count' => 0,
                'start_at' => $attributes['start_at'] ?? null, 'end_at' => $attributes['end_at'] ?? null,
            ]);
        });
    }

    private function nextSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $slug = $base;
        $suffix = 2;
        while (Campaign::query()->withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

return $slug;
    }
}
