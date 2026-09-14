<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_public_demo_content(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Ensemble, donnons vie aux projets qui comptent.')
            ->assertSee('Maquette — contenus et données fictifs')
            ->assertSee('Exemple fictif', false)
            ->assertSee('2 450 000 FCFA')
            ->assertSee('role="progressbar"', false)
            ->assertDontSee('href="'.'#"', false);
    }

    public function test_the_homepage_does_not_render_forbidden_claims_or_stitch_urls(): void
    {
        $response = $this->get('/');

        foreach ([
            'plateforme '.'certifiée',
            'garantie de '.'versement',
            'validation sous '.'24 h',
            'paiement '.'instantané',
            'paiements '.'100 % sécurisés',
            'retraits '.'directs',
            'création '.'100 % gratuite',
            'sans frais '.'cachés',
            'transparence '.'totale',
            'dons anonymes et '.'reçus disponibles',
            'lh3.'.'googleusercontent.com',
            'cdn.'.'tailwindcss.com',
        ] as $forbiddenContent) {
            $response->assertDontSee($forbiddenContent);
        }
    }

    public function test_the_campaign_progress_handles_zero_goal_reached_and_exceeded_values(): void
    {
        $zero = Blade::render('<x-campaign.progress :collected="$collected" :goal="$goal" />', ['collected' => 0, 'goal' => 100]);
        $reached = Blade::render('<x-campaign.progress :collected="$collected" :goal="$goal" />', ['collected' => 100, 'goal' => 100]);
        $exceeded = Blade::render('<x-campaign.progress :collected="$collected" :goal="$goal" />', ['collected' => 125, 'goal' => 100]);

        $this->assertStringContainsString('aria-valuenow="0"', $zero);
        $this->assertStringContainsString('aria-valuenow="100"', $reached);
        $this->assertStringContainsString('aria-valuenow="125"', $exceeded);
        $this->assertStringContainsString('width: 100%', $exceeded);
    }

    public function test_an_unknown_verification_badge_is_rendered_safely_as_unverified(): void
    {
        $badge = Blade::render('<x-campaign.verification-badge status="unknown" />');

        $this->assertStringContainsString('Non vérifié', $badge);
    }
}
