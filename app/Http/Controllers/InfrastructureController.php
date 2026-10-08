<?php

// InfrastructureController.php — InfrastructureController module.
//
// exports: InfrastructureController | InfrastructureController::index(): Response | InfrastructureController::create(): Response | InfrastructureController::store( StoreInfrastructureRequest $request, InfrastructureProvisioner $provisioner, ): RedirectResponse | InfrastructureController::show(Infrastructure $infrastructure, InfrastructureProvisioner $provisioner): Response | InfrastructureController::storeDatabaseUser( StoreInfrastructureDatabaseUserRequest $request, Infrastructure $infrastructure, AccessAccountManager $accounts, ): RedirectResponse | InfrastructureController::storeSftpUser( StoreInfrastructureSftpUserRequest $request, Infrastructure $infrastructure, AccessAccountManager $accounts, ): RedirectResponse | InfrastructureController::attachPhpmyadmin( AttachPhpmyadminDomainRequest $request, Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): RedirectResponse | InfrastructureController::attachPgadmin( AttachPgadminDomainRequest $request, Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): RedirectResponse | InfrastructureController::attachMinio( AttachMinioDomainRequest $request, Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): RedirectResponse | InfrastructureController::inspect( Infrastructure $infrastructure, ComposeRuntimeInspector $inspector, ): JsonResponse | InfrastructureController::deployStatus( Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): JsonResponse | InfrastructureController::update( UpdateInfrastructureRequest $request, Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): RedirectResponse | InfrastructureController::resetMysqlDatadir( RecreateMysqlDatadirRequest $request, Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): RedirectResponse | InfrastructureController::destroy( DestroyInfrastructureRequest $request, Infrastructure $infrastructure, InfrastructureProvisioner $provisioner, ): RedirectResponse
// used_by: routes/web.php
// rules:   Panel owns infra CRUD UI; Dokploy owns Swarm/git/deploy/logs via Services\Dokploy + Services\Infra.
//          One infra = one Dokploy compose on shared dokploy-network; isolated_networks stays false on create.
//          Never print DOKPLOY_API_KEY / DB / SFTP secrets in responses or logs.
//          Prefer failed/pending + last_error over destructive cleanup; destroy only via DestroyInfrastructureRequest guards.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | added architecture rules for agents
//          composer | cursor | 2026-09-21 | s_20260921_shared_net | Drop isolated_networks create guidance

namespace App\Http\Controllers;

use App\Enums\DatabasePrivilege;
use App\Http\Requests\AttachMinioDomainRequest;
use App\Http\Requests\AttachPgadminDomainRequest;
use App\Http\Requests\AttachPhpmyadminDomainRequest;
use App\Http\Requests\DestroyInfrastructureRequest;
use App\Http\Requests\RecreateMysqlDatadirRequest;
use App\Http\Requests\StoreInfrastructureDatabaseUserRequest;
use App\Http\Requests\StoreInfrastructureRequest;
use App\Http\Requests\StoreInfrastructureSftpUserRequest;
use App\Http\Requests\UpdateInfrastructureRequest;
use App\Models\Infrastructure;
use App\Services\Hosting\AccessAccountManager;
use App\Services\Infra\ComposeRuntimeInspector;
use App\Services\Infra\ComposeTemplate;
use App\Services\Infra\InfrastructureProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InfrastructureController extends Controller
{
    public function __construct(private ComposeTemplate $composeTemplate) {}

    public function index(): Response
    {
        return Inertia::render('Infrastructures/Index', [
            'infrastructures' => Infrastructure::query()
                ->orderBy('slug')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Infrastructures/Create', [
            'suggested_slug' => Infrastructure::nextSlug(),
            'catalog' => $this->composeTemplate->pickerCatalog(),
        ]);
    }

    public function store(
        StoreInfrastructureRequest $request,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $infrastructure = $provisioner->provision($request->validated());

        return $this->redirectToShow($infrastructure);
    }

    public function show(Infrastructure $infrastructure, InfrastructureProvisioner $provisioner): Response
    {
        $enabled = $this->composeTemplate->normalizeEnabled($infrastructure->enabled_services);
        $infrastructure->setRelation(
            'domains',
            $infrastructure->domains()->orWhere('infra_slug', $infrastructure->slug)->orderBy('fqdn')->orderBy('id')->get(),
        );
        $infrastructure->setRelation(
            'databaseAccounts',
            $infrastructure->databaseAccounts()->orWhere('infra_slug', $infrastructure->slug)->orderBy('database_name')->orderBy('username')->get(),
        );
        $infrastructure->setRelation(
            'sftpUsers',
            $infrastructure->sftpUsers()->orderBy('username')->get(),
        );
        $infrastructure->loadCount('domains');
        $infrastructure->databaseAccounts->each->revealPassword();
        $infrastructure->sftpUsers->each->revealPassword();
        $infrastructure->makeVisible([
            'mysql_admin_password',
            'mysql_root_password',
            'postgres_admin_password',
            'sftp_bootstrap_password',
            'pgadmin_password',
            'minio_root_password',
        ]);

        return Inertia::render('Infrastructures/Show', [
            'infrastructure' => $infrastructure,
            'credentials' => $infrastructure->stackCredentials(),
            'phpmyadmin_suggested_host' => $this->composeTemplate->phpmyadminHostname($infrastructure->slug),
            'pgadmin_suggested_host' => $this->composeTemplate->pgadminHostname($infrastructure->slug),
            'minio_suggested_host' => $this->composeTemplate->minioHostname($infrastructure->slug),
            'services' => collect($this->composeTemplate->pickerCatalog())
                ->map(fn (array $service): array => [
                    ...$service,
                    'enabled' => in_array($service['key'], $enabled, true),
                    'hostname' => $service['hostname_suffix'] !== null
                        ? $infrastructure->slug.'-'.$service['hostname_suffix']
                        : null,
                ])
                ->all(),
        ]);
    }

    public function storeDatabaseUser(
        StoreInfrastructureDatabaseUserRequest $request,
        Infrastructure $infrastructure,
        AccessAccountManager $accounts,
    ): RedirectResponse {
        $account = $accounts->createDatabaseUserForInfrastructure(
            $infrastructure,
            $request->sourceAccount(),
            DatabasePrivilege::from($request->validated('privilege')),
        );

        return $this->redirectToShow($infrastructure)
            ->with('revealed_credential', $accounts->revealDatabase($account, $account->password_encrypted));
    }

    public function storeSftpUser(
        StoreInfrastructureSftpUserRequest $request,
        Infrastructure $infrastructure,
        AccessAccountManager $accounts,
    ): RedirectResponse {
        $user = $accounts->createSftpUserForInfrastructure($infrastructure, $request->domain());

        return $this->redirectToShow($infrastructure)
            ->with('revealed_credential', $accounts->revealSftp($user, $user->password_encrypted));
    }

    public function attachPhpmyadmin(
        AttachPhpmyadminDomainRequest $request,
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();
        $provisioner->attachPhpmyadminDomain($infrastructure);

        return $this->redirectToShow($infrastructure);
    }

    public function attachPgadmin(
        AttachPgadminDomainRequest $request,
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();
        $provisioner->attachPgadminDomain($infrastructure);

        return $this->redirectToShow($infrastructure);
    }

    public function attachMinio(
        AttachMinioDomainRequest $request,
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();
        $provisioner->attachMinioDomain($infrastructure);

        return $this->redirectToShow($infrastructure);
    }

    public function inspect(
        Infrastructure $infrastructure,
        ComposeRuntimeInspector $inspector,
    ): JsonResponse {
        try {
            $service = request()->query('service');
            $services = is_string($service) && $service !== ''
                ? array_values(array_filter(array_map(trim(...), explode(',', $service))))
                : null;

            return response()->json($inspector->inspect(
                $infrastructure,
                max(1, min(10000, (int) request()->integer('tail', 300))),
                $services,
                request()->boolean('all'),
            ));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function deployStatus(
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): JsonResponse {
        try {
            return response()->json($provisioner->refreshDeployStatus($infrastructure));
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => $infrastructure->status,
                'last_error' => $exception->getMessage(),
                'compose_status' => null,
                'app_name' => null,
                'last_deployment' => null,
                'containers' => [],
            ], 422);
        }
    }

    public function update(
        UpdateInfrastructureRequest $request,
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $provisioner->updateStack($infrastructure, $request->validated('enabled_services'));

        return $this->redirectToShow($infrastructure);
    }

    public function resetMysqlDatadir(
        RecreateMysqlDatadirRequest $request,
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();
        $provisioner->resetMysqlDatadir($infrastructure);

        return $this->redirectToShow($infrastructure);
    }

    public function destroy(
        DestroyInfrastructureRequest $request,
        Infrastructure $infrastructure,
        InfrastructureProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();
        $provisioner->destroy($infrastructure);

        return redirect()->route('infrastructures.index');
    }

    private function redirectToShow(Infrastructure $infrastructure): RedirectResponse
    {
        $tab = request()->query('tab');
        $allowed = ['generale', 'servizi', 'domini', 'accessi', 'volumi', 'dokploy', 'pericolo'];

        return redirect()->route('infrastructures.show', [
            'infrastructure' => $infrastructure,
            ...(is_string($tab) && in_array($tab, $allowed, true) && $tab !== 'generale' ? ['tab' => $tab] : []),
        ]);
    }
}
