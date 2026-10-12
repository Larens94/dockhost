<?php

// DomainAliasManager.php — Create/delete alias and subdomain hosts on a Domain + Dokploy.
//
// exports: DomainAliasManager | create(Domain $domain, array $attributes): DomainAlias | delete(DomainAlias $alias): void
// used_by: app/Http/Controllers/DomainController.php
// rules:   web_service attaches hostname to the same Dokploy application (multi-tenant Host).
//          redirect_301 is stored for the panel; Traefik still routes to the app (Laravel may 301).
//          mail_service / sync_dns are panel flags only (no mailbox/DNS provider in v1).
//          Never invent a second Dokploy application for an alias.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Alias/subdomain sync manager
// message:

namespace App\Services\Hosting;

use App\Enums\DomainAliasKind;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\DomainAlias;
use App\Services\Dokploy\DokployClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainAliasManager
{
    public function __construct(private DokployClient $dokploy) {}

    /**
     * @param  array{
     *     kind: string,
     *     fqdn?: string|null,
     *     subdomain?: string|null,
     *     web_service?: bool,
     *     redirect_301?: bool,
     *     mail_service?: bool,
     *     sync_dns?: bool
     * }  $attributes
     */
    public function create(Domain $domain, array $attributes): DomainAlias
    {
        $kind = DomainAliasKind::from($attributes['kind']);
        $fqdn = $this->resolveFqdn($domain, $kind, $attributes);
        $this->assertFqdnAvailable($fqdn);

        $web = (bool) ($attributes['web_service'] ?? true);
        $redirect = (bool) ($attributes['redirect_301'] ?? false);
        $mail = (bool) ($attributes['mail_service'] ?? false);
        $syncDns = (bool) ($attributes['sync_dns'] ?? false);

        $dokployDomainId = null;

        if ($web) {
            $dokployDomainId = $this->attachDokployHostname($domain, $fqdn);
        }

        return DomainAlias::query()->create([
            'domain_id' => $domain->id,
            'fqdn' => $fqdn,
            'kind' => $kind,
            'web_service' => $web,
            'redirect_301' => $redirect,
            'mail_service' => $mail,
            'sync_dns' => $syncDns,
            'dokploy_domain_id' => $dokployDomainId,
        ]);
    }

    public function delete(DomainAlias $alias): void
    {
        $alias->loadMissing('domain.dokployApplication');

        if (filled($alias->dokploy_domain_id)) {
            try {
                $this->dokploy->deleteDomain(['domainId' => $alias->dokploy_domain_id]);
            } catch (Throwable) {
                // Panel row still removed; orphan Traefik host can be cleaned in Dokploy.
            }
        } elseif ($alias->web_service) {
            $this->detachDokployHostnameByHost($alias->domain, $alias->fqdn);
        }

        $alias->delete();
    }

    /**
     * @param  array{fqdn?: string|null, subdomain?: string|null}  $attributes
     */
    private function resolveFqdn(Domain $domain, DomainAliasKind $kind, array $attributes): string
    {
        if ($kind === DomainAliasKind::Subdomain) {
            $label = Str::lower(trim((string) ($attributes['subdomain'] ?? '')));
            $label = trim($label, '.');

            if ($label === '' || ! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $label)) {
                throw ValidationException::withMessages([
                    'subdomain' => __('panel.domains.aliases.invalid_subdomain'),
                ]);
            }

            return $label.'.'.Str::lower($domain->fqdn);
        }

        $fqdn = Str::lower(trim((string) ($attributes['fqdn'] ?? '')));
        $fqdn = preg_replace('/^www\./i', '', $fqdn) ?? $fqdn;

        if ($fqdn === '' || ! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $fqdn)) {
            throw ValidationException::withMessages([
                'fqdn' => __('panel.domains.aliases.invalid_fqdn'),
            ]);
        }

        if ($fqdn === Str::lower($domain->fqdn)) {
            throw ValidationException::withMessages([
                'fqdn' => __('panel.domains.aliases.same_as_primary'),
            ]);
        }

        return $fqdn;
    }

    private function assertFqdnAvailable(string $fqdn): void
    {
        if (Domain::query()->where('fqdn', $fqdn)->exists()) {
            throw ValidationException::withMessages([
                'fqdn' => __('panel.domains.aliases.fqdn_taken'),
            ]);
        }

        if (DomainAlias::query()->where('fqdn', $fqdn)->exists()) {
            throw ValidationException::withMessages([
                'fqdn' => __('panel.domains.aliases.fqdn_taken'),
            ]);
        }
    }

    private function attachDokployHostname(Domain $domain, string $fqdn): ?string
    {
        $domain->loadMissing('dokployApplication');
        $application = $domain->dokployApplication;

        if (! $application instanceof DokployApplication || ! filled($application->dokploy_application_id)) {
            throw ValidationException::withMessages([
                'web_service' => __('panel.domains.aliases.needs_application'),
            ]);
        }

        try {
            $created = $this->dokploy->createDomain([
                'host' => $fqdn,
                'path' => '/',
                'port' => (int) config('dokploy.app_port', 80),
                'https' => true,
                'certificateType' => 'letsencrypt',
                'applicationId' => $application->dokploy_application_id,
                'domainType' => 'application',
            ]);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'fqdn' => $this->dokploy->errorMessage($exception),
            ]);
        }

        $domainId = $this->dokploy->idFrom($created, 'domainId');

        return filled($domainId) ? $domainId : null;
    }

    private function detachDokployHostnameByHost(Domain $domain, string $fqdn): void
    {
        $domain->loadMissing('dokployApplication');
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! filled($applicationId)) {
            return;
        }

        try {
            $hosts = $this->dokploy->domainsByApplicationId($applicationId);
        } catch (Throwable) {
            return;
        }

        foreach ($hosts as $host) {
            if (! is_array($host)) {
                continue;
            }

            if (Str::lower((string) ($host['host'] ?? '')) !== Str::lower($fqdn)) {
                continue;
            }

            $domainId = (string) ($host['domainId'] ?? $host['id'] ?? '');

            if ($domainId === '') {
                continue;
            }

            try {
                $this->dokploy->deleteDomain(['domainId' => $domainId]);
            } catch (Throwable) {
            }

            break;
        }
    }
}
