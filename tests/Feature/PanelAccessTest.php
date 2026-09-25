<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_superadmin_can_open_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
    }

    public function test_dokploy_settings_say_when_the_api_is_not_configured(): void
    {
        config([
            'dockhost.dokploy.url' => null,
            'dockhost.dokploy.api_key' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.dokploy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.connected', false)
                ->where('settings.url', '')
                ->where('settings.api_key_masked', '')
            );
    }

    public function test_operators_cannot_enter_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'operator']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();

        auth()->logout();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_superadmin_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
