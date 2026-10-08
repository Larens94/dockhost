<?php

// LocalePreferenceTest.php — Session locale is stored for authenticated panel users and applied on later requests.
//
// exports: LocalePreferenceTest
// used_by: none
// rules:   Guests are redirected to login. Default locale is Italian. Logout ends the choice with the session.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Feature coverage for session locale.

namespace Tests\Feature;

use App\Models\User;
use App\Support\PanelLocale;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalePreferenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_open_or_update_the_language_setting(): void
    {
        $this->get(route('account.locale.show'))->assertRedirect(route('login'));

        $this->put(route('account.locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_setting_the_locale_stores_it_in_the_session_and_later_responses_use_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('lang="it"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'it')
                ->where('translations.layout.nav.customers', 'Clienti')
                ->where('translations.customers.title', 'Clienti'));

        $this->actingAs($user)
            ->put(route('account.locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('account.locale.show'))
            ->assertSessionHas(PanelLocale::SESSION_KEY, 'en');

        $this->actingAs($user)
            ->get(route('account.locale.show'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Locale')
                ->where('locale', 'en')
                ->where('flash.success', 'Language updated.')
                ->where('translations.locale_settings.title', 'Language'));

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations.customers.title', 'Customers')
                ->where('translations.layout.nav.spaces', 'Spaces'));

        $this->actingAs($user)
            ->get(route('domains.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations.domains.title', 'Domains'));
    }

    public function test_invalid_locale_is_rejected_and_the_panel_stays_italian(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('account.locale.show'))
            ->put(route('account.locale.update'), ['locale' => 'fr'])
            ->assertRedirect(route('account.locale.show'))
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing(PanelLocale::SESSION_KEY);

        $this->actingAs($user)
            ->withSession([PanelLocale::SESSION_KEY => 'fr'])
            ->get(route('subscriptions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'it')
                ->where('translations.spaces.title', 'Spazi'));
    }

    public function test_members_can_set_the_locale_and_logout_clears_it(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->put(route('account.locale.update'), ['locale' => 'en'])
            ->assertSessionHas(PanelLocale::SESSION_KEY, 'en');

        $this->actingAs($member)
            ->get(route('domains.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations.domains.my_hosting_title', 'My hosting'));

        $this->actingAs($member)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->actingAs($member)
            ->get(route('domains.index'))
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'it'));
    }
}
