<?php

// ResetDatabaseAccountPasswordTest.php — DB password reset must sync Dokploy DB_*.
//
// exports: ResetDatabaseAccountPasswordTest | ResetDatabaseAccountPasswordTest::test_reset_updates_mariadb_panel_and_dokploy_env(): void | ResetDatabaseAccountPasswordTest::test_reset_exposes_revealed_password_on_inertia_flash(): void | ResetDatabaseAccountPasswordTest::test_reset_still_flashes_password_when_dokploy_sync_fails(): void | ResetDatabaseAccountPasswordTest::test_reset_skips_dokploy_when_env_uses_another_username(): void | ResetDatabaseAccountPasswordTest::test_guests_cannot_reset_database_password(): void
// used_by: none (PHPUnit entry)
// rules:   Reset of the app DB user must saveEnvironment(DB_PASSWORD=new) and application.deploy.
//          Inertia flash must include revealed_credential so Domains/Show can show password once.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_db_pass_sync | Feature coverage for stale Dokploy DB_PASSWORD after reset
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_reveal_flash | Assert Inertia flash.revealed_credential after reset
// message:

namespace Tests\Feature;

use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\User;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class ResetDatabaseAccountPasswordTest extends TestCase
{
    public function test_reset_updates_mariadb_panel_and_dokploy_env(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-1',
                'env' => "APP_KEY=base64:keep\nDB_USERNAME=u_shop\nDB_PASSWORD=old-secret\nDB_HOST=infra1-mariadb\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'shop.example.test',
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $account = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'username' => 'u_shop',
            'database_name' => 'd_shop',
            'host' => 'infra1-mariadb',
            'privilege' => DatabasePrivilege::All,
            'password_encrypted' => 'old-secret',
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('replaceSiteUser')
            ->once()
            ->withArgs(function (
                string $database,
                string $username,
                string $password,
                DatabasePrivilege $privilege,
            ) use ($account): bool {
                return $database === $account->database_name
                    && $username === $account->username
                    && $password !== ''
                    && $password !== 'old-secret'
                    && $privilege === DatabasePrivilege::All;
            });
        $this->instance(MysqlProvisioner::class, $mysql);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('domains.database-users.reset-password', [$domain, $account]));

        $response->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']));
        $response->assertSessionHas('revealed_credential', function (array $payload) use ($domain): bool {
            return $payload['kind'] === 'database'
                && $payload['username'] === 'u_shop'
                && is_string($payload['password'] ?? null)
                && $payload['password'] !== ''
                && $payload['password'] !== 'old-secret'
                && (int) ($payload['domain_id'] ?? 0) === (int) $domain->id;
        });

        $newPassword = session('revealed_credential')['password'];
        $this->assertSame($newPassword, $account->refresh()->password_encrypted);

        Http::assertSent(function (Request $request) use ($newPassword): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) ($request['env'] ?? '');

            return $request['applicationId'] === 'app-1'
                && str_contains($env, 'DB_USERNAME=u_shop')
                && str_contains($env, 'DB_PASSWORD='.$newPassword)
                && ! str_contains($env, 'DB_PASSWORD=old-secret');
        });

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-1');
    }

    public function test_reset_exposes_revealed_password_on_inertia_flash(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-1',
                'env' => "DB_USERNAME=u_shop\nDB_PASSWORD=old-secret\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $account = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'username' => 'u_shop',
            'database_name' => 'd_shop',
            'privilege' => DatabasePrivilege::All,
            'password_encrypted' => 'old-secret',
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('replaceSiteUser')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->post(route('domains.database-users.reset-password', [$domain, $account]).'?tab=database')
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertSessionHas('revealed_credential');

        $revealed = session('revealed_credential');
        $this->assertIsArray($revealed);
        $this->assertNotSame('old-secret', $revealed['password'] ?? null);

        $this->actingAs($user)
            ->get(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Show')
                ->where('revealedCredential.kind', 'database')
                ->where('revealedCredential.username', 'u_shop')
                ->where('revealedCredential.domain_id', $domain->id)
                ->where('revealedCredential.password', $revealed['password'])
                ->where('flash.revealed_credential.password', $revealed['password'])
                ->where('flash.success', __('panel.domains.show.reset_db_user_password_done')));
    }

    public function test_reset_still_flashes_password_when_dokploy_sync_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-1',
                'env' => "DB_USERNAME=u_shop\nDB_PASSWORD=old-secret\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['message' => 'boom'], 500),
        ]);

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $account = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'username' => 'u_shop',
            'database_name' => 'd_shop',
            'privilege' => DatabasePrivilege::All,
            'password_encrypted' => 'old-secret',
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('replaceSiteUser')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('domains.database-users.reset-password', [$domain, $account]))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertSessionHas('revealed_credential', function (array $payload): bool {
                return ($payload['kind'] ?? null) === 'database'
                    && filled($payload['password'] ?? null)
                    && ($payload['password'] ?? null) !== 'old-secret';
            })
            ->assertSessionHas('error')
            ->assertSessionHas('success');

        $revealed = session('revealed_credential');

        $this->actingAs($user)
            ->get(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Show')
                ->where('flash.revealed_credential.password', $revealed['password'])
                ->where('revealedCredential.password', $revealed['password']));
    }

    public function test_reset_skips_dokploy_when_env_uses_another_username(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-1',
                'env' => "DB_USERNAME=u_primary\nDB_PASSWORD=keep-primary\n",
            ]),
        ]);

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'username' => 'u_primary',
            'database_name' => 'd_shop',
        ]);
        $readonly = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'username' => 'u_reader',
            'database_name' => 'd_shop',
            'privilege' => DatabasePrivilege::Select,
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('replaceSiteUser')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->actingAs(User::factory()->create())
            ->post(route('domains.database-users.reset-password', [$domain, $readonly]))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']));

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy');
    }

    public function test_guests_cannot_reset_database_password(): void
    {
        $domain = Domain::factory()->create();
        $account = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
        ]);

        $this->post(route('domains.database-users.reset-password', [$domain, $account]))
            ->assertRedirect(route('login'));
    }
}
