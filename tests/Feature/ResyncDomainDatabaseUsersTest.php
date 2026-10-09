<?php

// ResyncDomainDatabaseUsersTest.php — MariaDB resync action for domain database users.
//
// exports: ResyncDomainDatabaseUsersTest
// used_by: none
// rules:   Resync must call grantUser for each MySQL account with stored password.
// agent:   composer-2.5-fast | cursor | 2026-10-09 | s_pma_native_pass | Feature test resync route
// message:

namespace Tests\Feature;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\User;
use App\Services\Infra\MysqlProvisioner;
use Mockery;
use Tests\TestCase;

class ResyncDomainDatabaseUsersTest extends TestCase
{
    public function test_resync_calls_mysql_for_each_account_on_domain(): void
    {
        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $first = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'engine' => DatabaseEngine::Mysql,
            'database_name' => 'd_shop',
            'username' => 'u_shop',
            'privilege' => DatabasePrivilege::All,
            'password_encrypted' => 'secretone',
        ]);
        $second = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'engine' => DatabaseEngine::Mysql,
            'database_name' => 'd_shop',
            'username' => 'u_shop_extra',
            'privilege' => DatabasePrivilege::Select,
            'password_encrypted' => 'secrettwo',
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('resyncDatabaseAccount')
            ->once()
            ->withArgs(fn ($account): bool => $account->id === $first->id);
        $mysql->shouldReceive('resyncDatabaseAccount')
            ->once()
            ->withArgs(fn ($account): bool => $account->id === $second->id);
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->actingAs(User::factory()->create())
            ->post(route('domains.database-users.resync', $domain))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertSessionHas('success');
    }

    public function test_guests_cannot_resync_database_users(): void
    {
        $domain = Domain::factory()->create();

        $this->post(route('domains.database-users.resync', $domain))
            ->assertRedirect(route('login'));
    }
}
