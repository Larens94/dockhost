<?php


// SftpDaemonSyncTest.php — SftpDaemonSyncTest module.
//
// exports: SftpDaemonSyncTest | SftpDaemonSyncTest::test_posts_only_users_of_the_same_infrastructure(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Unit;

use App\Models\SftpUser;
use App\Services\Infra\SftpDaemonSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SftpDaemonSyncTest extends TestCase
{
    public function test_posts_only_users_of_the_same_infrastructure(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://infra1-sftp-sync:8787/sync' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
        ]);

        $first = $this->panelInfrastructure([
            'slug' => 'infra1',
            'sftp_sync_token' => 'token-one',
        ]);
        $second = $this->panelInfrastructure([
            'slug' => 'infra2',
            'sftp_sync_token' => 'token-two',
        ]);

        $firstUser = SftpUser::factory()->create([
            'infrastructure_id' => $first->id,
            'username' => 'sftp_first',
            'password_encrypted' => 'alpha123',
            'home_path' => '/data/site',
        ]);
        SftpUser::factory()->create([
            'infrastructure_id' => $second->id,
            'username' => 'sftp_second',
            'password_encrypted' => 'beta456',
            'home_path' => '/data/site',
        ]);

        app(SftpDaemonSync::class)->syncToContainer($firstUser);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'http://infra1-sftp-sync:8787/sync') {
                return false;
            }

            $users = $request['users'] ?? [];
            $names = array_column($users, 'username');

            return $request->hasHeader('Authorization', 'Bearer token-one')
                && in_array('sftp_first', $names, true)
                && ! in_array('sftp_second', $names, true)
                && in_array('/data/site', $request['directories'] ?? [], true)
                && in_array('/data/sftp_first', $request['directories'] ?? [], true);
        });

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'http://infra2-sftp-sync:8787/sync');
    }
}
