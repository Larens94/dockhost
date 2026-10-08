<?php

// DomainUiTest.php — DomainUiTest module.
//
// exports: DomainUiTest | DomainUiTest::test_subscription_show_is_a_domain_list_without_passwords(): void | DomainUiTest::test_create_and_show_pages_render(): void | DomainUiTest::test_guests_cannot_open_domain_pages(): void | DomainUiTest::test_laravel_domain_show_exposes_dokploy_application_url_for_log_link(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Tests\Feature;

use App\Models\DatabaseAccount;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\SftpUser;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DomainUiTest extends TestCase
{
    public function test_subscription_show_is_a_domain_list_without_passwords(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);
        $domain = Domain::factory()->create([
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'fqdn' => 'shop.acme.test',
            'infra_slug' => 'infra1',
        ]);
        DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'database_name' => 'd_shop_acme_test',
            'password_encrypted' => 'never-list-this',
        ]);
        SftpUser::factory()->create([
            'domain_id' => $domain->id,
            'username' => 'sftp_shop_acme_test',
            'password_encrypted' => 'never-list-sftp',
        ]);

        $this->actingAs($user)
            ->get(route('subscriptions.show', $subscription))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subscriptions/Show')
                ->missing('infrastructures')
                ->has('subscription.domains', 1)
                ->where('subscription.domains.0.fqdn', 'shop.acme.test')
                ->where('subscription.domains.0.database_accounts.0.database_name', 'd_shop_acme_test')
                ->where('subscription.domains.0.sftp_users.0.username', 'sftp_shop_acme_test')
                ->missing('subscription.domains.0.database_accounts.0.password')
                ->missing('subscription.domains.0.sftp_users.0.password'));
    }

    public function test_create_and_show_pages_render(): void
    {
        $this->panelInfrastructure();
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();
        $domain = Domain::factory()->create([
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'fqdn' => 'shop.acme.test',
        ]);

        $this->actingAs($user)
            ->get(route('subscriptions.domains.create', $subscription))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Create')
                ->where('subscription.id', $subscription->id)
                ->has('infrastructures')
                ->has('stacks'));

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Show')
                ->where('domain.fqdn', 'shop.acme.test')
                ->missing('domain.database_accounts.0.password'));
    }

    public function test_guests_cannot_open_domain_pages(): void
    {
        $subscription = Subscription::factory()->create();
        $domain = Domain::factory()->create([
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
        ]);

        $this->get(route('subscriptions.domains.create', $subscription))->assertRedirect(route('login'));
        $this->get(route('domains.show', $domain))->assertRedirect(route('login'));
    }

    public function test_laravel_domain_show_exposes_dokploy_application_url_for_log_link(): void
    {
        config(['dokploy.url' => 'https://dokploy.test']);
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-laravel',
                'env' => '',
            ]),
        ]);

        $infrastructure = $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();
        $domain = Domain::factory()->laravel()->create([
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'shop.acme.test',
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-laravel',
            'dokploy_environment_id' => 'env-1',
        ]);

        $expectedUrl = 'https://dokploy.test/dashboard/project/proj-1/environment/env-1/services/application/app-laravel';

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Show')
                ->where('domain.stack', 'laravel')
                ->where('domain.dokploy_application_url', $expectedUrl));
    }
}
