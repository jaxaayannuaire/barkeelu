<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\HomeDemoData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $campaigns = Campaign::query()
            ->publiclyListed()
            ->select([
                'id',
                'public_id',
                'owner_user_id',
                'owner_organization_id',
                'title',
                'slug',
                'description',
                'goal_amount',
                'net_collected_nominal',
                'donation_count',
                'currency',
                'featured',
                'published_at',
            ])
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();

        $demoMode = $campaigns->isEmpty();

        return view('pages.home', [
            'campaigns' => $demoMode ? collect(HomeDemoData::campaigns()) : $this->normalizeCampaigns($campaigns),
            'demoMode' => $demoMode,
        ]);
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return Collection<int, array<string, bool|int|string|null>>
     */
    private function normalizeCampaigns(Collection $campaigns): Collection
    {
        $placeholders = [
            'images/placeholders/campaign-health.svg',
            'images/placeholders/campaign-education.svg',
            'images/placeholders/campaign-community.svg',
        ];

        return $campaigns->map(function (Campaign $campaign) use ($placeholders): array {
            return [
                'publicId' => $campaign->public_id,
                'title' => $campaign->title,
                'slug' => $campaign->slug,
                'summary' => Str::limit(strip_tags($campaign->description), 180),
                'organizer' => $campaign->owner_organization_id !== null
                    ? 'Collecte portée par une organisation'
                    : 'Collecte individuelle',
                'image' => $placeholders[($campaign->id - 1) % count($placeholders)],
                'collected' => $campaign->net_collected_nominal,
                'goal' => $campaign->goal_amount,
                'contributions' => $campaign->donation_count,
                'verificationStatus' => null,
                'cause' => 'Collecte solidaire',
                'demoLabel' => null,
                'isDemo' => false,
                'currency' => $campaign->currency,
            ];
        });
    }
}
