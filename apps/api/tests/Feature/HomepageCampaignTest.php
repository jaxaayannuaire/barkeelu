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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomepageCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_database_uses_the_explicit_demo_fallback(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Maquette — contenus et données fictifs')
            ->assertSee('Exemple fictif');
    }

    public function test_only_published_public_campaigns_are_rendered_without_demo_content(): void
    {
        $owner = User::factory()->create(['name' => 'Nom privé', 'email' => 'prive@example.test']);
        $beneficiary = $this->beneficiary($owner, 'Bénéficiaire privé');
        $visible = $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, ['title' => 'Collecte réelle visible', 'description' => 'Description publique de la collecte.', 'net_collected_nominal' => 12500, 'goal_amount' => 10000, 'donation_count' => 7]);

        foreach (CampaignStatus::cases() as $status) {
            if ($status !== CampaignStatus::PUBLISHED) {
                $this->campaign($owner, $beneficiary, $status, CampaignVisibility::PUBLIC, ['title' => "État {$status->value}"]);
            }
        }
        foreach ([CampaignVisibility::UNLISTED, CampaignVisibility::PRIVATE, CampaignVisibility::TARGETED] as $visibility) {
            $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, $visibility, ['title' => "Visibilité {$visibility->value}"]);
        }

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee($visible->title)
            ->assertSee('12 500 FCFA')
            ->assertSee('125 % de l’objectif')
            ->assertDontSee('Maquette — contenus et données fictifs')
            ->assertDontSee('Exemple fictif')
            ->assertDontSee('Nom privé')
            ->assertDontSee('prive@example.test')
            ->assertDontSee('Bénéficiaire privé')
            ->assertDontSee('Identité vérifiée');

        foreach (CampaignStatus::cases() as $status) {
            if ($status !== CampaignStatus::PUBLISHED) {
                $response->assertDontSee("État {$status->value}");
            }
        }
        foreach ([CampaignVisibility::UNLISTED, CampaignVisibility::PRIVATE, CampaignVisibility::TARGETED] as $visibility) {
            $response->assertDontSee("Visibilité {$visibility->value}");
        }
    }

    public function test_the_homepage_is_limited_to_three_campaigns_in_deterministic_order(): void
    {
        $owner = User::factory()->create();
        $beneficiary = $this->beneficiary($owner);
        $oldFeatured = $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, ['title' => 'Featured ancien', 'featured' => true, 'published_at' => Carbon::parse('2026-01-01 08:00:00')]);
        $newFeatured = $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, ['title' => 'Featured récent', 'featured' => true, 'published_at' => Carbon::parse('2026-02-01 08:00:00')]);
        $newRegular = $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, ['title' => 'Régulière récente', 'featured' => false, 'published_at' => Carbon::parse('2026-03-01 08:00:00')]);
        $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC, ['title' => 'Régulière exclue', 'featured' => false, 'published_at' => Carbon::parse('2026-01-15 08:00:00')]);

        $content = $this->get('/')->getContent();

        $this->assertLessThan(strpos($content, $oldFeatured->title), strpos($content, $newFeatured->title));
        $this->assertLessThan(strpos($content, $newRegular->title), strpos($content, $oldFeatured->title));
        $this->assertStringNotContainsString('Régulière exclue', $content);
    }

    public function test_the_homepage_query_is_bounded_and_does_not_eager_load_relations(): void
    {
        $owner = User::factory()->create();
        $beneficiary = $this->beneficiary($owner);
        $this->campaign($owner, $beneficiary, CampaignStatus::PUBLISHED, CampaignVisibility::PUBLIC);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains($query, 'from "campaigns"'));
        DB::disableQueryLog();

        $this->assertCount(1, $queries);
        $this->assertStringContainsString('limit 3', $queries->first());
        $this->assertStringNotContainsString('join', strtolower($queries->first()));
    }

    private function beneficiary(User $user, string $displayName = 'Bénéficiaire fictif'): Beneficiary
    {
        return Beneficiary::query()->create([
            'public_id' => (string) Str::uuid(),
            'display_name' => $displayName,
            'type' => BeneficiaryType::INDIVIDUAL,
            'status' => BeneficiaryStatus::ACTIVE,
            'created_by_user_id' => $user->id,
        ]);
    }

    private function campaign(User $owner, Beneficiary $beneficiary, CampaignStatus $status, CampaignVisibility $visibility, array $overrides = []): Campaign
    {
        return Campaign::query()->create(array_merge([
            'public_id' => (string) Str::uuid(),
            'owner_user_id' => $owner->id,
            'owner_organization_id' => null,
            'created_by_user_id' => $owner->id,
            'beneficiary_id' => $beneficiary->id,
            'title' => 'Collecte '.Str::random(10),
            'slug' => 'collecte-'.Str::lower(Str::random(12)),
            'description' => 'Description de démonstration.',
            'goal_amount' => 1000,
            'currency' => 'XOF',
            'status' => $status,
            'fundraising_status' => CampaignFundraisingStatus::OPEN,
            'payout_status' => CampaignPayoutStatus::NOT_ELIGIBLE,
            'visibility' => $visibility,
            'featured' => false,
            'published_at' => $status === CampaignStatus::PUBLISHED ? now() : null,
            'net_collected_nominal' => 0,
            'donation_count' => 0,
        ], $overrides));
    }
}
