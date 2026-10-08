<?php


// AccessAccountTest.php — AccessAccountTest module.
//
// exports: AccessAccountTest | AccessAccountTest::test_creates_database_user_and_grants_select(): void | AccessAccountTest::test_rejects_database_user_when_domain_has_no_database(): void | AccessAccountTest::test_creates_sftp_user_with_alphanumeric_password(): void | AccessAccountTest::test_generated_sftp_password_never_contains_colon(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\SftpUser;
use App\Models\StorageShare;
use App\Models\User;
use App\Services\Hosting\AccessAccountManager;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class AccessAccountTest extends TestCase
{
    public function test_creates_database_user_and_grants_select(): void
    {
        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $source = DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'database_name' => 'd_shop_acme',
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('grantUser')
            ->once()
            ->withArgs(function (
                string $database,
                string $username,
                string $password,
                DatabasePrivilege $privilege,
            ) use ($source): bool {
                return $database === $source->database_name
                    && $username !== ''
                    && $password !== ''
                    && ! str_contains($password, ':')
                    && ctype_alnum($password)
                    && $privilege === DatabasePrivilege::Select;
            });
        $this->instance(MysqlProvisioner::class, $mysql);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('domains.database-users.store', $domain), [
                'privilege' => DatabasePrivilege::Select->value,
            ]);

        $response->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']));
        $response->assertSessionHas('revealed_credential', function (array $payload) use ($source): bool {
            return $payload['kind'] === 'database'
                && $payload['privilege'] === DatabasePrivilege::Select->value
                && $payload['database_name'] === $source->database_name
                && $payload['username'] !== ''
                && $payload['password'] !== ''
                && ! str_contains($payload['password'], ':');
        });

        $this->assertDatabaseHas('database_accounts', [
            'domain_id' => $domain->id,
            'database_name' => $source->database_name,
            'privilege' => DatabasePrivilege::Select->value,
        ]);
        $this->assertSame(2, DatabaseAccount::query()->where('database_name', $source->database_name)->count());
    }

    public function test_rejects_database_user_when_domain_has_no_database(): void
    {
        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldNotReceive('grantUser');
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->actingAs(User::factory()->create())
            ->post(route('domains.database-users.store', $domain), [
                'privilege' => DatabasePrivilege::All->value,
            ])
            ->assertSessionHasErrors('privilege');

        $this->assertDatabaseMissing('database_accounts', [
            'domain_id' => $domain->id,
        ]);
    }

    public function test_creates_sftp_user_with_alphanumeric_password(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'shop.acme.test',
        ]);
        StorageShare::factory()->create([
            'domain_id' => $domain->id,
            'path' => rtrim($infrastructure->storage_root, '/').'/acme/shop.acme.test',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('domains.sftp-users.store', $domain));

        $response->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'sftp']));
        $response->assertSessionHas('revealed_credential', function (array $payload): bool {
            return $payload['kind'] === 'sftp'
                && $payload['username'] !== ''
                && $payload['password'] !== ''
                && ! str_contains($payload['password'], ':')
                && ctype_alnum($payload['password']);
        });

        $user = SftpUser::query()->where('domain_id', $domain->id)->latest('id')->first();

        $this->assertInstanceOf(SftpUser::class, $user);
        $this->assertSame($infrastructure->id, $user->infrastructure_id);
        $this->assertSame(rtrim($infrastructure->storage_root, '/').'/acme/shop.acme.test', $user->home_path);
        $this->assertDoesNotMatchRegularExpression('/:/', $user->password_encrypted);
        $this->assertTrue(ctype_alnum($user->password_encrypted));
        Http::assertSent(fn ($request): bool => $request->url() === 'http://infra1-sftp-sync:8787/sync'
            && collect($request['users'] ?? [])->contains(fn (array $row): bool => ($row['username'] ?? null) === $user->username));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/docker.restartContainer'
            && $request['containerId'] === 'sftpcontainerid');
    }

    public function test_generated_sftp_password_never_contains_colon(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $password = AccessAccountManager::generatedPassword();

            $this->assertNotSame('', $password);
            $this->assertFalse(str_contains($password, ':'));
            $this->assertTrue(ctype_alnum($password));
        }
    }
}
