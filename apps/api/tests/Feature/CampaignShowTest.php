<?php

namespace Tests\Feature;

use App\Enums\BeneficiaryStatus;
use App\Enums\BeneficiaryType;
use App\Enums\CampaignFundraisingStatus;
use App\Enums\CampaignPayoutStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignVisibility;
use App\Models\Beneficiary;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_campaign_is_rendered_with_public_projection_and_indexable_metadata(): void
    {
        $campaign = $this->campaign(CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, [
            'title' => 'Une collecte publique',
            'description' => '<script>alert(1)</script><p>Récit public.</p>',
            'goal_amount' => 10000,
            'net_collected_nominal' => 12500,
            'donation_count' => 12,
            'distinct_donor_count' => 9,
        ]);

        $this->get(route('campaigns.show', ['slug' => $campaign->slug]))
            ->assertOk()
            ->assertSee('Une collecte publique')
            ->assertSee('Récit public.')
            ->assertSee('12 500 FCFA')
            ->assertSee('Contributions')
            ->assertSee('12')
            ->assertSee('9')
            ->assertSee('125 % de l’objectif')
            ->assertSee('width: 100%', false)
            ->assertSee('index,follow', false)
            ->assertSee('Vidéo de présentation non disponible')
            ->assertSee('Galerie non disponible')
            ->assertSee(route('donations.amount', ['slug' => $campaign->slug]), false)
            ->assertDontSee('Parcours de don web à venir')
            ->assertSee('Fonctionnalité à venir')
            ->assertSee('data-share', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee('Personne privée')
            ->assertDontSee('prive@example.test')
            ->assertDontSee('Bénéficiaire privé')
            ->assertDontSee('Identité vérifiée');
    }

    public function test_paused_and_closed_public_campaigns_keep_a_factual_disabled_support_cta(): void
    {
        foreach ([CampaignFundraisingStatus::PAUSED, CampaignFundraisingStatus::CLOSED] as $fundraisingStatus) {
            $campaign = $this->campaign(CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, [
                'fundraising_status' => $fundraisingStatus,
            ]);

            $this->get(route('campaigns.show', ['slug' => $campaign->slug]))
                ->assertOk()
                ->assertSee('Je soutiens')
                ->assertSee('disabled', false)
                ->assertSee($fundraisingStatus === CampaignFundraisingStatus::PAUSED
                    ? 'Collecte temporairement suspendue.'
                    : 'Collecte terminée.');
        }
    }

    public function test_unlisted_campaign_is_viewable_by_direct_url_and_not_indexable(): void
    {
        $campaign = $this->campaign(CampaignStatus::PUBLISHED, CampaignVisibility::UNLISTED);

        $this->get(route('campaigns.show', ['slug' => $campaign->slug]))
            ->assertOk()
            ->assertSee('noindex,nofollow', false);
    }

    public function test_non_public_or_non_published_campaigns_are_not_viewable(): void
    {
        foreach ([CampaignVisibility::PRIVATE, CampaignVisibility::TARGETED] as $visibility) {
            $campaign = $this->campaign(CampaignStatus::PUBLISHED, $visibility);
            $this->get(route('campaigns.show', ['slug' => $campaign->slug]))->assertNotFound();
        }

        foreach (CampaignStatus::cases() as $status) {
            if ($status !== CampaignStatus::PUBLISHED) {
                $campaign = $this->campaign($status, CampaignVisibility::PUBLIC);
                $this->get(route('campaigns.show', ['slug' => $campaign->slug]))->assertNotFound();
            }
        }
    }

    public function test_soft_deleted_campaign_is_not_viewable_and_no_sensitive_tables_are_queried(): void
    {
        $campaign = $this->campaign(CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC);
        $campaign->delete();
        $this->get(route('campaigns.show', ['slug' => $campaign->slug]))->assertNotFound();

        $visible = $this->campaign(CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('campaigns.show', ['slug' => $visible->slug]))->assertOk();
        $queries = strtolower(collect(DB::getQueryLog())->pluck('query')->join(' '));
        DB::disableQueryLog();

        $this->assertStringNotContainsString('kyc_', $queries);
        $this->assertStringNotContainsString('documents', $queries);
        $this->assertStringNotContainsString('donations', $queries);
        $this->assertStringNotContainsString('payments', $queries);
        $this->assertStringNotContainsString('webhook_events', $queries);
    }

    public function test_real_homepage_card_links_to_the_public_detail_without_linking_demo_cards(): void
    {
        $campaign = $this->campaign(CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC);

        $this->get('/')
            ->assertOk()
            ->assertSee(route('campaigns.show', ['slug' => $campaign->slug]), false);

        Campaign::query()->delete();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('/collectes/', false);
    }

    private function campaign(CampaignStatus $status, CampaignVisibility $visibility, array $overrides = []): Campaign
    {
        $owner = User::query()->first() ?? User::factory()->create(['name' => 'Personne privée', 'email' => 'prive@example.test']);
        $beneficiary = Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(),
            'display_name' => 'Bénéficiaire privé',
            'type' => BeneficiaryType::INDIVIDUAL,
            'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $owner->id,
        ]);

        return Campaign::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'owner_user_id' => $owner->id,
            'created_by_user_id' => $owner->id,
            'beneficiary_id' => $beneficiary->id,
            'title' => 'Collecte '.Str::random(10),
            'slug' => 'collecte-'.Str::lower(Str::random(12)),
            'description' => 'Description publique.',
            'goal_amount' => 1000,
            'currency' => 'XOF',
            'status' => $status,
            'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE,
            'visibility' => $visibility,
            'published_at' => $status === CampaignStatus::PUBLISHED ? now() : null,
            'net_collected_nominal' => 0,
            'donation_count' => 0,
            'distinct_donor_count' => 0,
        ], $overrides));
    }
}
