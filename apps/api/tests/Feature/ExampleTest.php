<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_public_foundation_is_rendered_without_forbidden_promises(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Barkeelu')
            ->assertSee('Aller au contenu')
            ->assertDontSee('garantie de '.'versement')
            ->assertDontSee('100% '.'sécurisé')
            ->assertDontSee('paiement '.'instantané')
            ->assertDontSee('cdn.'.'tailwindcss.com')
            ->assertDontSee('lh3.'.'googleusercontent.com');
    }

    public function test_the_public_layout_declares_vite_assets(): void
    {
        $layout = File::get(resource_path('views/layouts/public.blade.php'));

        $this->assertStringContainsString('@vite', $layout);
        $this->assertStringContainsString('resources/css/app.css', $layout);
        $this->assertStringContainsString('resources/js/app.js', $layout);
    }
}
