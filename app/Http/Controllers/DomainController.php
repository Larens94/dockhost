<?php

// DomainController.php — DomainController module.
//
// exports: DomainController | DomainController::create(Subscription $subscription): Response | DomainController::store( StoreDomainRequest $request, Subscription $subscription, DomainProvisioner $provisioner, AccessAccountManager $accounts, ): RedirectResponse | DomainController::show(Domain $domain): Response | DomainController::storeDatabase( StoreDomainDatabaseRequest $request, Domain $domain, DomainProvisioner $provisioner, AccessAccountManager $accounts, ): RedirectResponse | DomainController::storeDatabaseUser( StoreDomainDatabaseUserRequest $request, Domain $domain, AccessAccountManager $accounts, ): RedirectResponse | DomainController::storeSftpUser( StoreDomainSftpUserRequest $request, Domain $domain, AccessAccountManager $accounts, ): RedirectResponse | DomainController::attachLaravel( AttachLaravelRequest $request, Domain $domain, DomainProvisioner $provisioner, ): RedirectResponse | DomainController::alignLaravelEnv( AlignLaravelBootEnvRequest $request, Domain $domain, DokployApplicationAttacher $attacher, ): RedirectResponse | DomainController::applyStackPreset( ApplyDomainStackPresetRequest $request, Domain $domain, DokployApplicationAttacher $attacher, ): RedirectResponse | DomainController::applyLaravelDeployConfig( ApplyDomainStackPresetRequest $request, Domain $domain, DokployApplicationAttacher $attacher, ): RedirectResponse | DomainController::update( UpdateDomainRequest $request, Domain $domain, ): RedirectResponse | DomainController::destroy( DestroyDomainRequest $request, Domain $domain, DomainProvisioner $provisioner, ): RedirectResponse
// used_by: routes/web.php
// rules:   Domain wizard: customer space → domain → optional DB/SFTP → optional Laravel attach.
//          Flash revealed_credential once only; never re-read plaintext secrets from DB for display.
//          Attach Laravel uses infrastructure.dokploy_environment_id — not a pasted env ID from UI.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | domain/hosting + secrets rules
//          grok-4.7 | cursor | 2026-09-21 | s_20260921_laravel_deploy | Laravel deploy button writes Nixpacks env
//          composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | updatePhpSettings persists + Dokploy env merge
//          composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Site env, deploy sito, git summary on show
//          composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Email site DB/SFTP creds to domain owners on create

namespace App\Http\Controllers;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use App\Enums\DomainMemberRole;
use App\Enums\DomainStack;
use App\Http\Requests\AlignLaravelBootEnvRequest;
use App\Http\Requests\ApplyDomainStackPresetRequest;
use App\Http\Requests\AttachLaravelRequest;
use App\Http\Requests\DeployDomainSiteRequest;
use App\Http\Requests\DestroyDomainRequest;
use App\Http\Requests\ResyncDomainDatabaseUsersRequest;
use App\Http\Requests\StoreDomainDatabaseRequest;
use App\Http\Requests\StoreDomainDatabaseUserRequest;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\StoreDomainSftpUserRequest;
use App\Http\Requests\UpdateDomainPhpSettingsRequest;
use App\Http\Requests\UpdateDomainRequest;
use App\Http\Requests\UpdateDomainSiteEnvRequest;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Dokploy\DokployApplicationAttacher;
use App\Services\Hosting\AccessAccountManager;
use App\Services\Hosting\DomainProvisioner;
use App\Services\Hosting\DomainSiteEnvManager;
use App\Services\Infra\MysqlProvisioner;
use App\Services\Panel\DomainAccessMailer;
use App\Support\DomainPhpSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        $query = Domain::query()
            ->with(['customer', 'subscription.servicePlan'])
            ->orderBy('fqdn');

        if (! $user->isAdmin()) {
            $query->whereIn('domains.id', $user->domains()->pluck('domains.id'));
        }

        return Inertia::render('Domains/Index', [
            'domains' => $query->get(),
            'isAdmin' => $user->isAdmin(),
        ]);
    }

    public function create(Subscription $subscription): Response
    {
        $subscription->load(['customer', 'servicePlan']);

        return Inertia::render('Domains/Create', [
            'subscription' => $subscription,
            'infrastructures' => Infrastructure::assignablePayloads(),
            'stacks' => collect(DomainStack::choices())->map(fn (DomainStack $stack): array => [
                'value' => $stack->value,
                'label' => $stack->label(),
                'mark' => $stack->mark(),
                'creates_application' => $stack->createsApplication(),
                'is_laravel' => $stack->isLaravel(),
            ])->values()->all(),
        ]);
    }

    public function store(
        StoreDomainRequest $request,
        Subscription $subscription,
        DomainProvisioner $provisioner,
        AccessAccountManager $accounts,
    ): RedirectResponse {
        $domain = $provisioner->provision($subscription, $request->validated());

        return $this->redirectToDomain($domain)
            ->with('revealed_credential', $accounts->revealInitialDomainAccess($domain));
    }

    public function show(Domain $domain): Response
    {
        $user = request()->user();
        abort_unless($user instanceof User, 404);

        $domain->load([
            'customer',
            'subscription.customer',
            'subscription.servicePlan',
            'databaseAccounts',
            'storageShares',
            'sftpUsers',
            'dokployApplication',
            'infrastructure',
        ]);

        $canManageAccess = $user->canManageDomainMembers($domain);

        if ($canManageAccess) {
            $domain->load([
                'members' => fn ($query) => $query->orderBy('name'),
            ]);
        }

        $infrastructures = $user->isAdmin()
            ? Infrastructure::assignablePayloads()
            : collect([$domain->infrastructure?->domainMemberPayload()])->filter()->values();

        return Inertia::render('Domains/Show', [
            'domain' => $this->domainPayloadForPanel($domain, $user),
            'domainMembers' => $canManageAccess
                ? $domain->members->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role ?? 'developer',
                    'role_label' => DomainMemberRole::tryFrom($member->pivot->role ?? 'developer')?->label() ?? 'Sviluppatore',
                ])->values()->all()
                : [],
            'canManageAccess' => $canManageAccess,
            'canViewSubscription' => $user->isAdmin(),
            'canMutateHosting' => $user->canMutateDomainHosting($domain),
            'canOpenDokploy' => $user->isAdmin(),
            'canEditFqdn' => $user->isAdmin(),
            'canDestroyDomain' => $user->isAdmin(),
            'dokployAccessNote' => $user->isAdmin()
                ? null
                : 'La console Dokploy condivide il progetto infrastruttura: non invitiamo gli utenti hosting al progetto Dokploy. Usa questo pannello per database, SFTP e Laravel Toolkit sul tuo dominio.',
            'infrastructures' => $infrastructures,
            'stackPresets' => app(DokployApplicationAttacher::class)->nixpacksPreset(
                $domain->stack ?? DomainStack::None,
            ),
            'laravelDeployPreset' => ($domain->stack ?? DomainStack::None)->isLaravel()
                ? app(DokployApplicationAttacher::class)->laravelDeployPreset()
                : [],
            'phpSettings' => DomainPhpSettings::resolved($domain->php_settings),
            'phpSettingsApplicable' => ($domain->stack ?? DomainStack::None)->createsApplication(),
            'siteHosting' => app(DomainSiteEnvManager::class)->panelState($domain),
        ]);
    }

    public function updateSiteEnv(
        UpdateDomainSiteEnvRequest $request,
        Domain $domain,
        DomainSiteEnvManager $siteEnv,
        AuditLogger $audit,
    ): RedirectResponse {
        $result = $siteEnv->mergeEntries($domain, $request->normalizedEntries());

        $audit->record('domain.site_env.save', $domain, [
            'fqdn' => $domain->fqdn,
            'added' => $result['added'],
            'updated' => $result['updated'],
        ]);

        $keys = [...$result['added'], ...$result['updated']];
        $message = $keys === []
            ? __('panel.domains.flash.site_env_unchanged')
            : __('panel.domains.flash.site_env_updated', ['keys' => implode(', ', $keys)]);

        return $this->redirectToDomain($domain, 'generale')->with('success', $message);
    }

    public function deploySite(
        DeployDomainSiteRequest $request,
        Domain $domain,
        DomainSiteEnvManager $siteEnv,
        AuditLogger $audit,
    ): RedirectResponse {
        $request->validated();
        $siteEnv->deploy($domain);

        $audit->record('domain.deploy', $domain, [
            'fqdn' => $domain->fqdn,
            'trigger' => 'panel',
        ]);

        return $this->redirectToDomain($domain, 'generale')->with('success', __('panel.domains.flash.deploy_started'));
    }

    public function updatePhpSettings(
        UpdateDomainPhpSettingsRequest $request,
        Domain $domain,
        DokployApplicationAttacher $attacher,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $deployNow = (bool) ($validated['deploy_now'] ?? false);
        unset($validated['deploy_now']);

        $domain->update(['php_settings' => $validated]);

        $sync = $attacher->syncPhpSettings($domain->fresh(), $deployNow);

        $audit->record('domain.php_settings.save', $domain, [
            'fqdn' => $domain->fqdn,
            'deploy_now' => $deployNow,
            'settings' => $validated,
        ]);

        if ($sync['deployed'] ?? false) {
            $audit->record('domain.deploy', $domain, [
                'fqdn' => $domain->fqdn,
                'trigger' => 'php_settings',
            ]);
        }

        if ($sync['env_synced']) {
            $message = $deployNow
                ? __('panel.domains.flash.php_deployed')
                : __('panel.domains.flash.php_needs_deploy');
        } else {
            $message = __('panel.domains.flash.php_no_app');
        }

        return $this->redirectToDomain($domain, 'generale')->with('success', $message);
    }

    public function storeDatabase(
        StoreDomainDatabaseRequest $request,
        Domain $domain,
        DomainProvisioner $provisioner,
        AccessAccountManager $accounts,
        DomainAccessMailer $accessMailer,
    ): RedirectResponse {
        $account = $provisioner->provisionDatabase(
            $domain,
            DatabaseEngine::from($request->validated('engine')),
            $request->validated('infra_slug'),
        );

        $revealed = $accounts->revealDatabase($account, $account->password_encrypted);
        $this->emailSiteCredentialIfPossible($request, $domain, $accessMailer, $revealed);

        return $this->redirectToDomain($domain, 'database')
            ->with('revealed_credential', $revealed);
    }

    public function storeDatabaseUser(
        StoreDomainDatabaseUserRequest $request,
        Domain $domain,
        AccessAccountManager $accounts,
        DomainAccessMailer $accessMailer,
    ): RedirectResponse {
        $account = $accounts->createDatabaseUserForDomain(
            $domain,
            DatabasePrivilege::from($request->validated('privilege')),
        );

        $revealed = $accounts->revealDatabase($account, $account->password_encrypted);
        $this->emailSiteCredentialIfPossible($request, $domain, $accessMailer, $revealed);

        return $this->redirectToDomain($domain, 'database')
            ->with('revealed_credential', $revealed);
    }

    public function resyncDatabaseUsers(
        ResyncDomainDatabaseUsersRequest $request,
        Domain $domain,
        MysqlProvisioner $mysql,
    ): RedirectResponse {
        $domain->load('databaseAccounts.infrastructure');

        $synced = 0;

        foreach ($domain->databaseAccounts as $account) {
            if ($account->engine !== DatabaseEngine::Mysql) {
                continue;
            }

            $mysql->resyncDatabaseAccount($account);
            $synced++;
        }

        if ($synced === 0) {
            throw ValidationException::withMessages([
                'database' => __('panel.domains.show.resync_mysql_users_none'),
            ]);
        }

        return $this->redirectToDomain($domain, 'database')
            ->with('success', __('panel.domains.show.resync_mysql_users_done', ['count' => $synced]));
    }

    public function storeSftpUser(
        StoreDomainSftpUserRequest $request,
        Domain $domain,
        AccessAccountManager $accounts,
        DomainAccessMailer $accessMailer,
    ): RedirectResponse {
        $request->validated();
        $user = $accounts->createSftpUserForDomain($domain);

        $revealed = $accounts->revealSftp($user, $user->password_encrypted);
        $this->emailSiteCredentialIfPossible($request, $domain, $accessMailer, $revealed);

        return $this->redirectToDomain($domain, 'sftp')
            ->with('revealed_credential', $revealed);
    }

    public function attachLaravel(
        AttachLaravelRequest $request,
        Domain $domain,
        DomainProvisioner $provisioner,
    ): RedirectResponse {
        try {
            $provisioner->attachLaravel($domain, $request->validated());
        } catch (ValidationException $exception) {
            $messages = $exception->errors();

            if (isset($messages['stack'])) {
                throw ValidationException::withMessages([
                    'attach_laravel' => $messages['stack'],
                ]);
            }

            throw $exception;
        }

        return $this->redirectToDomain($domain, 'laravel');
    }

    public function alignLaravelEnv(
        AlignLaravelBootEnvRequest $request,
        Domain $domain,
        DokployApplicationAttacher $attacher,
    ): RedirectResponse {
        $request->validated();
        $result = $attacher->alignBootEnv($domain);

        $message = $result['generated_app_key']
            ? __('panel.domains.flash.boot_generated')
            : ($result['added'] === []
                ? __('panel.domains.flash.boot_already')
                : __('panel.domains.flash.boot_aligned'));

        return $this->redirectToDomain($domain, 'laravel')->with('success', $message);
    }

    public function applyStackPreset(
        ApplyDomainStackPresetRequest $request,
        Domain $domain,
        DokployApplicationAttacher $attacher,
    ): RedirectResponse {
        $request->validated();
        $result = $attacher->applyStackPreset($domain);

        $message = $result['added'] === []
            ? __('panel.domains.flash.preset_already')
            : __('panel.domains.flash.preset_applied', ['keys' => implode(', ', $result['added'])]);

        return $this->redirectToDomain($domain, 'sito')->with('success', $message);
    }

    public function applyLaravelDeployConfig(
        ApplyDomainStackPresetRequest $request,
        Domain $domain,
        DokployApplicationAttacher $attacher,
    ): RedirectResponse {
        $request->validated();
        $result = $attacher->applyLaravelDeployConfig($domain);

        $written = [...$result['updated'], ...$result['added']];
        $message = $written === []
            ? __('panel.domains.flash.deploy_config_already')
            : __('panel.domains.flash.deploy_config_written', ['keys' => implode(', ', $written)]);

        return redirect()->route('domains.show', [
            'domain' => $domain,
            'tab' => 'laravel',
            'section' => 'deploy',
        ])->with('success', $message);
    }

    public function update(
        UpdateDomainRequest $request,
        Domain $domain,
    ): RedirectResponse {
        $domain->update($request->validated());

        return $this->redirectToDomain($domain);
    }

    public function destroy(
        DestroyDomainRequest $request,
        Domain $domain,
        DomainProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();
        $subscriptionId = $domain->subscription_id;
        $provisioner->decommission($domain);

        return redirect()->route('subscriptions.show', $subscriptionId);
    }

    /**
     * @param  array{kind: string, username: string, password: string, privilege?: string, database_name?: string}  $revealed
     */
    private function emailSiteCredentialIfPossible(
        Request $request,
        Domain $domain,
        DomainAccessMailer $accessMailer,
        array $revealed,
    ): void {
        $actor = $request->user();

        if (! $actor instanceof User) {
            return;
        }

        $accessMailer->sendSiteCredentialToOwners($domain, $revealed, $actor);
    }

    private function redirectToDomain(Domain $domain, ?string $preferredTab = null): RedirectResponse
    {
        $tab = request()->query('tab', $preferredTab);
        $allowed = ['generale', 'database', 'sftp', 'laravel', 'sito', 'pericolo'];

        return redirect()->route('domains.show', [
            'domain' => $domain,
            ...(is_string($tab) && in_array($tab, $allowed, true) && $tab !== 'generale' ? ['tab' => $tab] : []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function domainPayloadForPanel(Domain $domain, User $user): array
    {
        $payload = $domain->toArray();

        if (! $user->isAdmin()) {
            $payload['dokploy_application_url'] = null;
        }

        return $payload;
    }
}
