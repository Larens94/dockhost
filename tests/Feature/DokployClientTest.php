<?php

// DokployClientTest.php — DokployClientTest module.
//
// exports: DokployClientTest | DokployClientTest::test_requests_use_x_api_key_and_documented_endpoints(): void | DokployClientTest::test_read_compose_logs_accepts_string_payload_and_query_params(): void | DokployClientTest::test_boolean_success_json_is_normalized_to_empty_array(): void | DokployClientTest::test_application_containers_and_deployments_use_dokploy_http(): void | DokployClientTest::test_missing_dokploy_procedure_is_mapped_to_italian(): void | DokployClientTest::test_remove_mount_posts_mount_id_and_normalizes_boolean_success(): void | DokployClientTest::test_project_helpers_unwrap_nested_payloads_and_collect_compose_ids(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Tests\Feature;

use App\Services\Dokploy\DokployClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokployClientTest extends TestCase
{
    public function test_requests_use_x_api_key_and_documented_endpoints(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.create' => Http::response(['applicationId' => 'app-1']),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.saveGitProvider' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'dom-1']),
            'https://dokploy.test/api/project.all' => Http::response([['projectId' => 'proj-1']]),
            'https://dokploy.test/api/project.create' => Http::response([
                'project' => ['projectId' => 'proj-2'],
                'environment' => ['environmentId' => 'env-2'],
            ]),
            'https://dokploy.test/api/environment.create' => Http::response(['environmentId' => 'env-3']),
            'https://dokploy.test/api/compose.create' => Http::response(['composeId' => 'compose-1']),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'done',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                ['deploymentId' => 'dep-1', 'status' => 'done'],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-mariadb-1', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'mounts' => [
                    ['mountPath' => '/data/infra1'],
                    ['mountPath' => '/etc/sftp/infra1'],
                ],
            ]),
            'https://dokploy.test/api/mounts.create' => Http::response(['ok' => true]),
        ]);

        $client = new DokployClient;

        $this->assertSame(['applicationId' => 'app-1'], $client->createApplication([
            'name' => 'shop',
            'environmentId' => 'env-1',
        ]));
        $this->assertSame(['ok' => true], $client->saveEnvironment([
            'applicationId' => 'app-1',
            'env' => 'APP_KEY=base64:test',
        ]));
        $this->assertSame(['ok' => true], $client->deploy(['applicationId' => 'app-1']));
        $this->assertSame(['domainId' => 'dom-1'], $client->createDomain([
            'host' => 'shop.acme.test',
            'applicationId' => 'app-1',
        ]));
        $this->assertSame(['ok' => true], $client->saveGitProvider([
            'applicationId' => 'app-1',
            'customGitUrl' => 'https://gitlab.example.com/acme/shop.git',
        ]));
        $this->assertSame([['projectId' => 'proj-1']], $client->allProjects());
        $createdProject = $client->createProject(['name' => 'infra1']);
        $this->assertSame('proj-2', $client->idFrom($createdProject, 'projectId'));
        $this->assertSame('env-2', $client->idFrom($createdProject, 'environmentId'));
        $this->assertSame(['environmentId' => 'env-3'], $client->createEnvironment([
            'name' => 'production',
            'projectId' => 'proj-2',
        ]));
        $this->assertSame(['composeId' => 'compose-1'], $client->createCompose([
            'name' => 'infra1',
            'environmentId' => 'env-1',
        ]));
        $this->assertSame(['ok' => true], $client->updateCompose([
            'composeId' => 'compose-1',
            'isolatedDeployment' => false,
        ]));
        $this->assertSame(['ok' => true], $client->saveComposeEnvironment([
            'composeId' => 'compose-1',
            'env' => 'POSTGRES_PASSWORD=secret',
        ]));
        $this->assertSame(['ok' => true], $client->deployCompose(['composeId' => 'compose-1']));
        $this->assertSame([
            'composeId' => 'compose-1',
            'appName' => 'infra1',
            'composeStatus' => 'done',
        ], $client->getCompose('compose-1'));
        $this->assertSame([
            ['deploymentId' => 'dep-1', 'status' => 'done'],
        ], $client->composeDeployments('compose-1'));
        $this->assertSame([
            ['Name' => '/infra1-mariadb-1', 'State' => 'running'],
        ], $client->containersByAppName('infra1'));
        $this->assertSame(['/data/infra1', '/etc/sftp/infra1'], $client->applicationMountPaths('panel-app'));
        $this->assertSame(['ok' => true], $client->createMount([
            'type' => 'volume',
            'volumeName' => 'infra1_data',
            'mountPath' => '/data/infra1',
            'serviceId' => 'panel-app',
        ]));
        $this->assertSame(['domainId' => 'dom-1'], $client->createComposeDomain([
            'host' => 'pma-infra1.cloud.silicoreautomation.com',
            'composeId' => 'compose-1',
            'serviceName' => 'phpmyadmin',
            'port' => 80,
        ]));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && $request['applicationId'] === 'app-1'
            && $request['env'] === 'APP_KEY=base64:test'
            && $request['buildArgs'] === ''
            && $request['buildSecrets'] === ''
            && $request['createEnvFile'] === false);
        Http::assertSentCount(18);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.one?applicationId=panel-app');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.one?composeId=compose-1');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/deployment.allByCompose?composeId=compose-1');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/docker.getContainersByAppNameMatch?appName=infra1&appType=docker-compose');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pma-infra1.cloud.silicoreautomation.com'
            && $request['path'] === '/'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-1'
            && $request['serviceName'] === 'phpmyadmin');
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'testing')
            && str_contains($request->url(), '/api/'));
    }

    public function test_read_compose_logs_accepts_string_payload_and_query_params(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.readLogs*' => Http::response("GRANT_FAIL Access denied for user 'root'@'10.0.1.76'"),
        ]);

        $client = new DokployClient;

        $this->assertSame(
            "GRANT_FAIL Access denied for user 'root'@'10.0.1.76'",
            $client->readComposeLogs('compose-1', 'abc123', 50, '1h', 'denied'),
        );

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://dokploy.test/api/compose.readLogs?')
                && $query['composeId'] === 'compose-1'
                && $query['containerId'] === 'abc123'
                && (int) $query['tail'] === 50
                && $query['since'] === '1h'
                && $query['search'] === 'denied';
        });
    }

    public function test_boolean_success_json_is_normalized_to_empty_array(): void
    {
        Http::preventStrayRequests();
        $ok = Http::response('true', 200, ['Content-Type' => 'application/json']);
        Http::fake([
            'https://dokploy.test/api/compose.update' => $ok,
            'https://dokploy.test/api/compose.saveEnvironment' => $ok,
            'https://dokploy.test/api/compose.deploy' => $ok,
            'https://dokploy.test/api/domain.create' => $ok,
            'https://dokploy.test/api/mounts.create' => $ok,
        ]);

        $client = new DokployClient;

        $this->assertSame([], $client->updateCompose(['composeId' => 'compose-1']));
        $this->assertSame([], $client->saveComposeEnvironment(['composeId' => 'compose-1']));
        $this->assertSame([], $client->deployCompose(['composeId' => 'compose-1']));
        $this->assertSame([], $client->createDomain(['host' => 'pma-infra1.test']));
        $this->assertSame([], $client->createComposeDomain([
            'host' => 'pma-infra1.test',
            'composeId' => 'compose-1',
            'serviceName' => 'phpmyadmin',
            'port' => 80,
        ]));
        $this->assertSame([], $client->createMount([
            'type' => 'volume',
            'volumeName' => 'infra1_data',
        ]));
    }

    public function test_application_containers_and_deployments_use_dokploy_http(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/docker.getContainersByAppLabel*' => Http::response([
                ['Id' => 'appcontainerid', 'Name' => '/shop', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/deployment.all*' => Http::response([
                ['deploymentId' => 'dep-1', 'title' => 'Manual deployment', 'status' => 'done'],
            ]),
        ]);

        $client = new DokployClient;

        $this->assertSame([
            ['Id' => 'appcontainerid', 'Name' => '/shop', 'State' => 'running'],
        ], $client->applicationContainers('shop'));
        $this->assertSame([
            ['deploymentId' => 'dep-1', 'title' => 'Manual deployment', 'status' => 'done'],
        ], $client->applicationDeployments('app-1'));
        $this->assertFalse($client->httpExecIsAvailable());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/docker.getContainersByAppLabel?appName=shop&type=standalone');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/deployment.all?applicationId=app-1');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'docker.executeCommand'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'docker.getContainersByAppNameMatch'));
    }

    public function test_application_containers_include_name_match_when_labeled_are_stopped(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/docker.getContainersByAppLabel*' => Http::response([
                ['containerId' => 'oldstopped01', 'name' => 'shop-old', 'state' => 'exited'],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['containerId' => 'e0987fdacbf8', 'name' => 'shop.1.live', 'state' => 'running'],
            ]),
        ]);

        $containers = (new DokployClient)->applicationContainers('shop');

        $this->assertSame('oldstopped01', $containers[0]['containerId']);
        $this->assertSame('e0987fdacbf8', $containers[1]['containerId']);
    }

    public function test_missing_dokploy_procedure_is_mapped_to_italian(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'message' => 'Not found',
                'code' => 'NOT_FOUND',
            ], 404),
        ]);

        $client = new DokployClient;

        try {
            $client->getApplication('missing-app');
            $this->fail('Expected a request exception for the missing procedure payload.');
        } catch (RequestException $exception) {
            $this->assertSame(
                'Dokploy non espone questa procedura HTTP (404). Per i comandi nel container usa il terminale su Dokploy.',
                $client->errorMessage($exception),
            );
        }
    }

    public function test_remove_mount_posts_mount_id_and_normalizes_boolean_success(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/mounts.remove' => Http::response('true', 200, ['Content-Type' => 'application/json']),
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'mounts' => [
                    ['mountId' => 'mnt-1', 'mountPath' => '/data/infra1', 'volumeName' => 'infra1_data'],
                    ['id' => 'mnt-2', 'mountPath' => '/etc/sftp/infra1'],
                    ['mountPath' => ''],
                ],
            ]),
        ]);

        $client = new DokployClient;

        $this->assertSame([], $client->removeMount('mnt-extra'));
        $this->assertSame([
            [
                'mountId' => 'mnt-1',
                'mountPath' => '/data/infra1',
                'volumeName' => 'infra1_data',
            ],
            [
                'mountId' => 'mnt-2',
                'mountPath' => '/etc/sftp/infra1',
                'volumeName' => null,
            ],
        ], $client->applicationMounts('panel-app'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/mounts.remove'
            && $request['mountId'] === 'mnt-extra');
    }

    public function test_project_helpers_unwrap_nested_payloads_and_collect_compose_ids(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/project.all' => Http::response([
                'data' => [
                    ['projectId' => 'proj-1', 'name' => 'infra1'],
                    ['projectId' => 'proj-2', 'name' => 'infra2'],
                ],
            ]),
            'https://dokploy.test/api/project.one*' => Http::response([
                'projectId' => 'proj-1',
                'environments' => [
                    [
                        'compose' => [
                            ['composeId' => 'compose-a'],
                        ],
                        'composes' => [
                            ['id' => 'compose-b'],
                        ],
                    ],
                ],
            ]),
            'https://dokploy.test/api/compose.stop' => Http::response(['ok' => true]),
            'https://dokploy.test/api/settings.cleanUnusedVolumes' => Http::response(['ok' => true]),
        ]);

        $client = new DokployClient;

        $this->assertSame([
            ['projectId' => 'proj-1', 'name' => 'infra1'],
            ['projectId' => 'proj-2', 'name' => 'infra2'],
        ], $client->allProjects());

        $project = $client->getProject('proj-1');
        $this->assertSame(['compose-a', 'compose-b'], $client->composeIdsFromProject($project));
        $this->assertSame(['ok' => true], $client->stopCompose(['composeId' => 'compose-a']));
        $this->assertSame(['ok' => true], $client->cleanUnusedVolumes());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.one?projectId=proj-1');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/settings.cleanUnusedVolumes');
    }
}
