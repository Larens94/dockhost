<?php

// SftpDaemonSyncTest.php — SftpDaemonSyncTest module.
//
// exports: SftpDaemonSyncTest | SftpDaemonSyncTest::test_posts_only_users_of_the_same_infrastructure(): void
// used_by: none
// rules:   Sync payload includes uid + directory; home_path aligns to /{storage_root}/{username} jail.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer-2.5-fast | cursor | 2026-10-11 | s_sftp_jail | Assert uid/directory + jail home align
// message:

namespace Tests\Unit;

use App\Models\Domain;
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
            'http://sftp-sync-a-sftp-sync:8787/sync' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/sftp-sync-a-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
        ]);

        $first = $this->panelInfrastructure([
            'slug' => 'sftp-sync-a',
            'sftp_sync_token' => 'token-one',
            'sftp_host' => 'sftp-sync-a-sftp',
        ]);
        $second = $this->panelInfrastructure([
            'slug' => 'sftp-sync-b',
            'sftp_sync_token' => 'token-two',
            'sftp_host' => 'sftp-sync-b-sftp',
        ]);

        $legacyHome = rtrim($first->storage_root, '/').'/legacy/site';
        $firstDomain = Domain::factory()->create([
            'infrastructure_id' => $first->id,
            'infra_slug' => $first->slug,
        ]);
        $secondDomain = Domain::factory()->create([
            'infrastructure_id' => $second->id,
            'infra_slug' => $second->slug,
        ]);
        $firstUser = SftpUser::factory()->create([
            'infrastructure_id' => $first->id,
            'domain_id' => $firstDomain->id,
            'username' => 'sftp_first',
            'password_encrypted' => 'alpha123',
            'home_path' => $legacyHome,
        ]);
        SftpUser::factory()->create([
            'infrastructure_id' => $second->id,
            'domain_id' => $secondDomain->id,
            'username' => 'sftp_second',
            'password_encrypted' => 'beta456',
            'home_path' => $legacyHome,
        ]);

        app(SftpDaemonSync::class)->syncToContainer($firstUser);

        $expectedUid = 10000 + (int) $firstUser->id;
        $jail = rtrim($first->storage_root, '/').'/sftp_first';
        $containerLegacy = '/data/legacy/site';

        Http::assertSent(function (Request $request) use ($expectedUid, $containerLegacy): bool {
            if ($request->url() !== 'http://sftp-sync-a-sftp-sync:8787/sync') {
                return false;
            }

            $users = $request['users'] ?? [];
            $names = array_column($users, 'username');
            $row = collect($users)->firstWhere('username', 'sftp_first');

            return $request->hasHeader('Authorization', 'Bearer token-one')
                && in_array('sftp_first', $names, true)
                && ! in_array('sftp_second', $names, true)
                && is_array($row)
                && ($row['directory'] ?? null) === $containerLegacy
                && (int) ($row['uid'] ?? 0) === $expectedUid
                && in_array($containerLegacy, $request['directories'] ?? [], true)
                && in_array('/data/sftp_first', $request['directories'] ?? [], true);
        });

        $this->assertSame($jail, $firstUser->fresh()->home_path);

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'http://sftp-sync-b-sftp-sync:8787/sync');
    }
}
