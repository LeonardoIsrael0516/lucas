<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureInstalled;
use App\Models\Plugin;
use App\Models\User;
use Tests\TestCase;

class LucasPluginDashboardTest extends TestCase
{
    public function test_area_membros_redirects_to_lucas_dashboard_when_plugin_enabled(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        Plugin::create([
            'slug' => 'lucas',
            'name' => 'Lucas',
            'version' => '1.0.0',
            'is_enabled' => true,
        ]);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => 1,
        ]);

        $response = $this->actingAs($aluno)->get('/area-membros');

        $response->assertRedirect(route('lucas.dashboard'));
    }

    public function test_lucas_dashboard_renders_plugin_inertia_page(): void
    {
        $this->withoutMiddleware(EnsureInstalled::class);

        Plugin::create([
            'slug' => 'lucas',
            'name' => 'Lucas',
            'version' => '1.0.0',
            'is_enabled' => true,
        ]);

        $aluno = User::factory()->create([
            'role' => User::ROLE_ALUNO,
            'tenant_id' => 1,
        ]);

        $response = $this->actingAs($aluno)->get('/lucas/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Plugin/Lucas/Dashboard'));
    }
}
