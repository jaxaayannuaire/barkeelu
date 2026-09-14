<?php

namespace App\Http\Controllers\Web;

use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignVisibility;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CampaignShowController extends Controller
{
    public function __invoke(string $slug): View
    {
        $campaign = Campaign::query()
            ->publiclyViewable()
            ->select([
                'owner_organization_id',
                'title',
                'slug',
                'description',
                'goal_amount',
                'currency',
                'net_collected_nominal',
                'donation_count',
                'distinct_donor_count',
                'fundraising_status',
                'visibility',
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $description = trim(strip_tags($campaign->description));

        return view('pages.campaigns.show', [
            'campaign' => $campaign,
            'descriptionParagraphs' => array_values(array_filter(preg_split('/\R{2,}/u', $description) ?: [])),
            'organizerLabel' => $campaign->owner_organization_id !== null
                ? 'Collecte portée par une organisation'
                : 'Collecte individuelle',
            'fundraisingLabel' => $this->fundraisingLabel($campaign->fundraising_status),
            'canonical' => route('campaigns.show', ['slug' => $campaign->slug]),
            'metaDescription' => Str::limit($description, 160),
            'robots' => $campaign->visibility === CampaignVisibility::PUBLIC ? 'index,follow' : 'noindex,nofollow',
        ]);
    }

    private function fundraisingLabel(CampaignFundraisingStatus $status): string
    {
        return match ($status) {
            CampaignFundraisingStatus::NOT_STARTED => 'Collecte pas encore ouverte',
            CampaignFundraisingStatus::PAUSED => 'Collecte temporairement suspendue',
            CampaignFundraisingStatus::CLOSED => 'Collecte terminée',
            CampaignFundraisingStatus::OPEN => 'Collecte ouverte',
        };
    }
}
