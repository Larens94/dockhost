<?php

// AuthenticationTest.php — AuthenticationTest module.
//
// exports: AuthenticationTest | AuthenticationTest::test_login_page_renders_inertia(): void | AuthenticationTest::test_https_app_url_forces_https_scheme_for_assets(): void | AuthenticationTest::test_guests_are_redirected_to_login_for_hosting_routes(): void | AuthenticationTest::test_users_can_authenticate_with_valid_credentials(): void | AuthenticationTest::test_users_cannot_authenticate_with_invalid_password(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_renders_inertia(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_https_app_url_forces_https_scheme_for_assets(): void
    {
        config(['app.url' => 'https://dokhosts.example.com']);

        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', asset('build/assets/app.css'));
    }

    public function test_guests_are_redirected_to_login_for_hosting_routes(): void
    {
        $this->get('/customers')->assertRedirect(route('login'));
        $this->get('/customers/create')->assertRedirect(route('login'));
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('customers.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_member_login_redirects_to_domains_index(): void
    {
        $member = User::factory()->member()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $member->email,
            'password' => 'password',
        ])->assertRedirect(route('domains.index'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
