<?php

namespace App\Services\Campaigns;

use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransitionCampaign
{
    public function transition(Campaign $campaign, CampaignStatus $target): Campaign
    {
        $allowed = [CampaignStatus::DRAFT->value => [CampaignStatus::SUBMITTED], CampaignStatus::SUBMITTED->value => [CampaignStatus::UNDER_REVIEW, CampaignStatus::CANCELLED], CampaignStatus::UNDER_REVIEW->value => [CampaignStatus::PUBLISHED, CampaignStatus::REJECTED], CampaignStatus::PUBLISHED->value => [CampaignStatus::PAUSED, CampaignStatus::ENDED], CampaignStatus::PAUSED->value => [CampaignStatus::PUBLISHED, CampaignStatus::ENDED], CampaignStatus::REJECTED->value => [CampaignStatus::CANCELLED], CampaignStatus::ENDED->value => [CampaignStatus::CLOSED]];
        if (! in_array($target, $allowed[$campaign->status->value] ?? [], true)) {
            throw new DomainException('Transition de campagne invalide.');
        }

        return DB::transaction(function () use ($campaign, $target): Campaign {
            $changes = ['status' => $target];
            if ($target === CampaignStatus::PUBLISHED) {
                $changes['published_at'] = $campaign->published_at ?? now();
                $changes['fundraising_status'] = $campaign->start_at === null || $campaign->start_at->lte(now()) ? CampaignFundraisingStatus::OPEN : CampaignFundraisingStatus::NOT_STARTED;
            }
            if ($target === CampaignStatus::PAUSED) {
                $changes['fundraising_status'] = CampaignFundraisingStatus::PAUSED;
            }
            if (in_array($target, [CampaignStatus::ENDED, CampaignStatus::CLOSED], true)) {
                $changes['fundraising_status'] = CampaignFundraisingStatus::CLOSED;
            }
            if ($target === CampaignStatus::CLOSED) {
                $changes['closed_at'] = now();
            }
            $campaign->update($changes);

            return $campaign->refresh();
        });
    }
}
