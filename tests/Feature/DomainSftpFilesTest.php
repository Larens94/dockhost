<?php

// DomainSftpFilesTest.php — Domain SFTP file browser API.
//
// exports: DomainSftpFilesTest
// agent: composer-2.5-fast | cursor | 2026-11-05 | s_sftp_files

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SftpUser;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainSftpFilesTest extends TestCase
{
    public function test_lists_files_via_sidecar(): void
    {
        Http::fake([
            'http://infra1-sftp-sync:8787/files/list' => Http::response([
                'path' => 'storage/app/public',
                'entries' => [
                    ['name' => 'events', 'type' => 'dir', 'size' => 4096, 'mtime' => 1],
                ],
            ]),
        ]);

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $sftpUser = SftpUser::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'username' => 'sftp_shop_acme_test',
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson(route('domains.sftp-files.index', [$domain, $sftpUser]));

        $response->assertOk();
        $response->assertJsonPath('entries.0.name', 'events');

        Http::assertSent(function (Request $request) use ($sftpUser): bool {
            return $request->url() === 'http://infra1-sftp-sync:8787/files/list'
                && $request['jail'] === $sftpUser->username
                && $request->hasHeader('Authorization');
        });
    }

    public function test_rejects_sftp_user_from_other_domain(): void
    {
        Http::fake();

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $other = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $sftpUser = SftpUser::factory()->create([
            'domain_id' => $other->id,
            'infrastructure_id' => $infrastructure->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('domains.sftp-files.index', [$domain, $sftpUser]))
            ->assertNotFound();
    }
}
