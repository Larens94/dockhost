// client.mjs — Dokploy API client for DokHosts MCP (read + controlled writes).
//
// exports: loadEnvFile | repoRoot | serviceFromName | DokployApi | normalizeContainers | listStacks | findStack | findApplication | listContainers | listDeployments | listDomains | listServices | listMounts | listApplications | getApplicationSummary | getComposeEnvSummary | getComposeDeployStatus | getApplicationDeployStatus | listApplicationContainers | restartApplicationContainer | readApplicationLogs | getServerHealth | inspectStack | readServiceLogs | deployComposeStack | deployApplicationByRef | patchApplicationEnv | restartStackService | createApiFromEnv
// used_by: mcp/dokploy/server.mjs
//         mcp/dokploy/smoke.mjs
// rules:   MCP MUST redact secrets in env summaries and logs — never return raw PASSWORD/APP_KEY/MYSQL_* values
// rules:   compose.update / full compose YAML not exposed via MCP — use panel or repo infra files
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_mcp_v13 | deploy status rollup, compose env summary, application containers + restart

import { readFileSync, existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const DEFAULT_LOG_SERVICES = ['mysql-grants', 'mariadb', 'phpmyadmin'];
const SERVICE_NAMES = [
    'phpmyadmin',
    'pgadmin',
    'mysql-grants',
    'mariadb',
    'postgres',
    'minio',
    'redis',
    'sftp-users-init',
    'sftp-sync',
    'sftp',
];

export function loadEnvFile(path) {
    if (!path || !existsSync(path)) {
        return;
    }

    const text = readFileSync(path, 'utf8');

    for (const rawLine of text.split('\n')) {
        const line = rawLine.trim();

        if (line === '' || line.startsWith('#')) {
            continue;
        }

        const eq = line.indexOf('=');

        if (eq < 1) {
            continue;
        }

        const key = line.slice(0, eq).trim();

        if (process.env[key] !== undefined) {
            continue;
        }

        let value = line.slice(eq + 1).trim();

        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }

        process.env[key] = value;
    }
}

export function repoRoot() {
    return resolve(dirname(fileURLToPath(import.meta.url)), '../..');
}

function unwrapList(payload) {
    if (!payload || typeof payload !== 'object') {
        return [];
    }

    if (Array.isArray(payload)) {
        return payload.filter((item) => item && typeof item === 'object');
    }

    for (const path of ['result.data', 'data', 'projects', 'containers', 'items']) {
        const nested = getPath(payload, path);

        if (Array.isArray(nested)) {
            return nested.filter((item) => item && typeof item === 'object');
        }
    }

    return [payload];
}

function getPath(object, path) {
    return path.split('.').reduce((current, key) => {
        if (current && typeof current === 'object' && key in current) {
            return current[key];
        }

        return undefined;
    }, object);
}

function stringifyLogs(json, fallbackBody = '') {
    if (typeof json === 'string') {
        return json;
    }

    if (json == null) {
        return fallbackBody;
    }

    if (Array.isArray(json) && json.every((item) => typeof item === 'string')) {
        return json.join('\n');
    }

    if (typeof json === 'object') {
        for (const path of ['logs', 'data', 'result.data', 'message']) {
            const value = getPath(json, path);

            if (typeof value === 'string') {
                return value;
            }
        }

        return JSON.stringify(json);
    }

    return fallbackBody;
}

function redact(text) {
    return String(text)
        .replace(/IDENTIFIED BY '[^']*'/gi, "IDENTIFIED BY '***'")
        .replace(/(password[=:]\s*)\S+/gi, '$1***');
}

const SENSITIVE_ENV_KEY =
    /^(APP_KEY|.*_(PASSWORD|SECRET|TOKEN|PRIVATE_KEY|API_KEY|CREDENTIAL|SIGNING_KEY)|MYSQL_.+|POSTGRES_.+|REDIS_PASSWORD|AWS_SECRET|MAIL_PASSWORD)$/i;

function parseEnvLines(envText) {
    const map = new Map();

    for (const rawLine of String(envText ?? '').split('\n')) {
        const line = rawLine.trim();

        if (line === '' || line.startsWith('#')) {
            continue;
        }

        const eq = line.indexOf('=');

        if (eq < 1) {
            continue;
        }

        const key = line.slice(0, eq).trim();
        const value = line.slice(eq + 1);

        map.set(key, value);
    }

    return map;
}

function serializeEnvLines(map) {
    return [...map.entries()].map(([key, value]) => `${key}=${value}`).join('\n');
}

function envKeyIsSensitive(key) {
    return SENSITIVE_ENV_KEY.test(String(key));
}

export function summarizeEnvForMcp(envText) {
    const variables = [];

    for (const [key, value] of parseEnvLines(envText)) {
        if (envKeyIsSensitive(key)) {
            variables.push({
                key,
                redacted: true,
                length: value.length,
                alphanumeric: /^[a-zA-Z0-9]+$/.test(value),
            });
        } else {
            variables.push({ key, value });
        }
    }

    variables.sort((a, b) => a.key.localeCompare(b.key));

    return variables;
}

export async function findApplication(api, { name = null, applicationId = null }) {
    const apps = await listApplications(api);
    let app = null;

    if (applicationId) {
        app = apps.find((item) => item.application_id === applicationId) ?? null;
    } else if (name) {
        const needle = String(name).toLowerCase();
        const matches = apps.filter(
            (item) =>
                String(item.name ?? '').toLowerCase() === needle
                || String(item.app_name ?? '').toLowerCase() === needle,
        );

        if (matches.length > 1) {
            throw new Error(
                `Più applicazioni «${name}». Specifica applicationId. Trovate: ${matches.map((item) => item.application_id).join(', ')}`,
            );
        }

        app = matches[0] ?? null;
    }

    if (!app?.application_id) {
        throw new Error(`Applicazione «${name || applicationId}» non trovata.`);
    }

    return app;
}

export function serviceFromName(name) {
    const lower = String(name).toLowerCase();

    return SERVICE_NAMES.find((service) => lower.includes(service)) ?? null;
}

function usableContainerId(id, name) {
    for (const candidate of [id, name]) {
        if (candidate && /^[a-zA-Z0-9.\-_]+$/.test(candidate)) {
            return candidate;
        }
    }

    return null;
}

function looksDenied(body) {
    const lower = String(body).toLowerCase();

    return lower.includes('access denied') || lower.includes('error 1045');
}

export class DokployApi {
    constructor({ url, apiKey }) {
        this.baseUrl = `${String(url ?? '').replace(/\/$/, '')}/api`;
        this.apiKey = String(apiKey ?? '');
    }

    assertConfigured() {
        if (this.baseUrl === '/api' || this.apiKey === '') {
            throw new Error('DOKPLOY_URL e DOKPLOY_API_KEY devono essere nel .env del repo.');
        }
    }

    async get(procedure, query = {}) {
        this.assertConfigured();
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(query)) {
            if (value === undefined || value === null || value === '') {
                continue;
            }

            params.set(key, String(value));
        }

        const suffix = params.size > 0 ? `?${params}` : '';
        const response = await fetch(`${this.baseUrl}/${procedure}${suffix}`, {
            headers: {
                accept: 'application/json',
                'x-api-key': this.apiKey,
            },
        });
        const body = await response.text();

        if (!response.ok) {
            throw new Error(`Dokploy ${procedure} HTTP ${response.status}: ${body.slice(0, 400)}`);
        }

        if (body.trim() === '') {
            return null;
        }

        try {
            return JSON.parse(body);
        } catch {
            return body;
        }
    }

    async post(procedure, payload = {}, timeoutMs = 15000) {
        this.assertConfigured();
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeoutMs);

        try {
            const response = await fetch(`${this.baseUrl}/${procedure}`, {
                method: 'POST',
                headers: {
                    accept: 'application/json',
                    'content-type': 'application/json',
                    'x-api-key': this.apiKey,
                },
                body: JSON.stringify(payload),
                signal: controller.signal,
            });
            const body = await response.text();

            if (!response.ok) {
                throw new Error(`Dokploy ${procedure} HTTP ${response.status}: ${body.slice(0, 400)}`);
            }

            if (body.trim() === '') {
                return {};
            }

            try {
                return JSON.parse(body);
            } catch {
                return { raw: body };
            }
        } catch (error) {
            if (error instanceof Error && error.name === 'AbortError') {
                throw new Error(`Dokploy ${procedure} timeout dopo ${timeoutMs}ms (deploy può continuare in background).`);
            }

            throw error;
        } finally {
            clearTimeout(timer);
        }
    }

    async getApplication(applicationId) {
        return await this.get('application.one', { applicationId });
    }

    async saveApplicationEnvironment(applicationId, envText) {
        return await this.post('application.saveEnvironment', {
            applicationId,
            env: envText,
            buildArgs: '',
            buildSecrets: '',
            createEnvFile: false,
        });
    }

    async deployApplication(applicationId) {
        return await this.post('application.deploy', { applicationId }, 120_000);
    }

    async deployCompose(composeId) {
        return await this.post('compose.deploy', { composeId }, 120_000);
    }

    async restartContainer(containerId) {
        return await this.post('docker.restartContainer', { containerId }, 60_000);
    }

    async allProjects() {
        return unwrapList(await this.get('project.all'));
    }

    async getProject(projectId) {
        return await this.get('project.one', { projectId });
    }

    composeIdsFromProject(project) {
        const ids = [];
        const environments = project?.environments ?? getPath(project, 'result.data.environments') ?? [];

        if (!Array.isArray(environments)) {
            return [];
        }

        for (const environment of environments) {
            if (!environment || typeof environment !== 'object') {
                continue;
            }

            for (const key of ['compose', 'composes']) {
                const services = environment[key];

                if (!Array.isArray(services)) {
                    continue;
                }

                for (const compose of services) {
                    const composeId = compose?.composeId ?? compose?.id;

                    if (typeof composeId === 'string' && composeId !== '') {
                        ids.push(composeId);
                    }
                }
            }
        }

        return [...new Set(ids)];
    }

    async getCompose(composeId) {
        return await this.get('compose.one', { composeId });
    }

    async composeDeployments(composeId) {
        return unwrapList(await this.get('deployment.allByCompose', { composeId }));
    }

    async applicationDeployments(applicationId) {
        return unwrapList(await this.get('deployment.all', { applicationId }));
    }

    async containersByAppName(appName, appType = 'docker-compose') {
        return unwrapList(
            await this.get('docker.getContainersByAppNameMatch', {
                appName,
                appType,
            }),
        );
    }

    async applicationContainers(appName) {
        try {
            const labeled = unwrapList(
                await this.get('docker.getContainersByAppLabel', {
                    appName,
                    type: 'standalone',
                }),
            );

            if (labeled.length > 0) {
                return labeled;
            }
        } catch {
            // fall through
        }

        try {
            return unwrapList(
                await this.get('docker.getContainersByAppNameMatch', {
                    appName,
                }),
            );
        } catch {
            return [];
        }
    }

    async domainsByCompose(composeId) {
        return unwrapList(await this.get('domain.byComposeId', { composeId }));
    }

    async loadServices(composeId) {
        const payload = await this.get('compose.loadServices', { composeId });

        if (Array.isArray(payload) && payload.every((item) => typeof item === 'string')) {
            return payload;
        }

        return unwrapList(payload).map((item) => item.name ?? item.serviceName ?? item).filter(Boolean);
    }

    async loadMountsByService(composeId, serviceName) {
        return unwrapList(await this.get('compose.loadMountsByService', { composeId, serviceName }));
    }

    async readApplicationLogs(applicationId, tail = 300) {
        const json = await this.get('application.readLogs', { applicationId, tail });

        return redact(stringifyLogs(json));
    }

    async getServerHealth() {
        return await this.get('docker.getServerHealth');
    }

    async domainsByApplication(applicationId) {
        return unwrapList(await this.get('domain.byApplicationId', { applicationId }));
    }

    async readComposeLogs(composeId, containerId, tail = 300, since = 'all') {
        const json = await this.get('compose.readLogs', {
            composeId,
            containerId,
            tail,
            since,
        });

        return redact(stringifyLogs(json));
    }
}

export function normalizeContainers(raw) {
    return raw.map((item) => {
        const names = item.Names ?? item.names;
        const firstName = Array.isArray(names) ? names[0] : names;
        const name = String(item.Name ?? item.name ?? firstName ?? '').replace(/^\//, '');
        const rawId = String(item.Id ?? item.ID ?? item.id ?? item.containerId ?? '').replace(/^\//, '');
        const stateValue = item.State ?? item.state;
        const state =
            stateValue && typeof stateValue === 'object'
                ? String(stateValue.Status ?? stateValue.status ?? '')
                : String(stateValue ?? '');
        const status = String(item.Status ?? item.status ?? '');

        return {
            id: usableContainerId(rawId, name),
            name: name || '(senza nome)',
            service: serviceFromName(name),
            state: state.toLowerCase() || statusFromDocker(status),
            status,
        };
    });
}

function statusFromDocker(status) {
    const lower = status.toLowerCase();

    if (lower.startsWith('up') || lower.includes('running')) {
        return 'running';
    }

    if (lower.includes('restart')) {
        return 'restarting';
    }

    if (lower.includes('exit')) {
        return 'exited';
    }

    return lower;
}

function findings(containers, logs) {
    const out = [];
    const logByService = Object.fromEntries(logs.map((row) => [row.service ?? row.name, row]));
    const mariadb = containers.find((container) => container.service === 'mariadb');
    const grants = containers.find((container) => container.service === 'mysql-grants');
    const mariadbLogs = logByService.mariadb;
    const grantsLogs = logByService['mysql-grants'];
    const pmaLogs = logByService.phpmyadmin;

    if (!mariadb) {
        out.push({
            code: 'mariadb_down',
            severity: 'error',
            service: 'mariadb',
            message: 'Container MariaDB assente su Dokploy.',
        });
    } else if (!['running', 'healthy'].includes(mariadb.state)) {
        out.push({
            code: 'mariadb_down',
            severity: 'error',
            service: 'mariadb',
            message: `MariaDB non è in esecuzione (${mariadb.state}).`,
        });
    } else if (mariadb.status.toLowerCase().includes('unhealthy')) {
        out.push({
            code: 'mariadb_unhealthy',
            severity: 'error',
            service: 'mariadb',
            message: `MariaDB è unhealthy: ${mariadb.status}`,
        });
    }

    if (mariadbLogs && looksDenied(mariadbLogs.body)) {
        out.push({
            code: 'mariadb_auth_denied',
            severity: 'warning',
            service: 'mariadb',
            message: 'Nei log MariaDB c’è Access denied.',
        });
    }

    if (!grants) {
        out.push({
            code: 'grants_missing',
            severity: 'warning',
            service: 'mysql-grants',
            message: 'Container mysql-grants assente.',
        });
    } else if (grantsLogs) {
        if (grantsLogs.error) {
            out.push({
                code: 'grants_logs_unavailable',
                severity: 'warning',
                service: 'mysql-grants',
                message: grantsLogs.error,
            });
        } else if (grantsLogs.body.includes('GRANT_OK')) {
            out.push({
                code: 'grants_aligned',
                severity: 'ok',
                service: 'mysql-grants',
                message: 'mysql-grants ha allineato gli utenti alle password del pannello.',
            });
        } else if (looksDenied(grantsLogs.body) || grantsLogs.body.includes('GRANT_FAIL')) {
            out.push({
                code: 'grants_root_mismatch',
                severity: 'error',
                service: 'mysql-grants',
                message:
                    'mysql-grants non si autentica come root: il datadir MariaDB non usa la password del pannello.',
            });
        } else if (String(grants.status).includes('Exited (1)')) {
            out.push({
                code: 'grants_failed',
                severity: 'error',
                service: 'mysql-grants',
                message: `mysql-grants è uscito con codice 1. ${grants.status}`,
            });
        } else {
            out.push({
                code: 'grants_inconclusive',
                severity: 'warning',
                service: 'mysql-grants',
                message: 'Log grants senza GRANT_OK/GRANT_FAIL.',
            });
        }
    }

    if (pmaLogs && looksDenied(pmaLogs.body)) {
        out.push({
            code: 'phpmyadmin_denied',
            severity: 'error',
            service: 'phpmyadmin',
            message: 'phpMyAdmin riceve Access denied da MariaDB.',
        });
    }

    if (out.length === 0) {
        out.push({
            code: 'ok',
            severity: 'ok',
            service: null,
            message: 'Nessuna anomalia evidente nei log letti.',
        });
    }

    return out;
}

export async function listStacks(api) {
    const projects = await api.allProjects();
    const stacks = [];

    for (const project of projects) {
        const name = String(project.name ?? project.appName ?? '');
        const projectId = String(project.projectId ?? project.id ?? '');

        if (projectId === '') {
            continue;
        }

        let composeIds = [];

        try {
            composeIds = api.composeIdsFromProject(await api.getProject(projectId));
        } catch {
            composeIds = [];
        }

        stacks.push({ name, projectId, composeIds });
    }

    return stacks;
}

export async function findStack(api, name) {
    const stacks = await listStacks(api);
    const stack = stacks.find((item) => item.name.toLowerCase() === String(name).toLowerCase());

    if (!stack) {
        throw new Error(`Stack Dokploy «${name}» non trovato. Disponibili: ${stacks.map((item) => item.name).join(', ')}`);
    }

    if (stack.composeIds.length === 0) {
        throw new Error(`Il progetto «${name}» non ha un compose (solo applicazioni o vuoto).`);
    }

    const composeId = stack.composeIds[0];
    const compose = await api.getCompose(composeId);

    return {
        ...stack,
        composeId,
        compose,
        appName: String(compose.appName ?? compose.name ?? stack.name),
    };
}

function summarizeDomain(domain) {
    return {
        host: domain.host ?? null,
        https: Boolean(domain.https),
        enabled: domain.enabled !== false,
        service: domain.serviceName ?? null,
        port: domain.port ?? null,
        path: domain.path ?? '/',
        certificate: domain.certificateType ?? null,
        domain_type: domain.domainType ?? null,
    };
}

function summarizeMount(mount) {
    return {
        type: mount.Type ?? mount.type ?? null,
        name: mount.Name ?? mount.name ?? mount.volumeName ?? null,
        destination: mount.Destination ?? mount.destination ?? mount.mountPath ?? null,
        mode: mount.Mode ?? mount.mode ?? null,
        rw: mount.RW ?? mount.rw ?? null,
    };
}

function summarizeDeployment(row) {
    const status = String(row.status ?? '').toLowerCase();

    return {
        deployment_id: row.deploymentId ?? row.id ?? null,
        status: row.status ?? null,
        title: row.title ?? row.description ?? null,
        created_at: row.createdAt ?? row.startedAt ?? null,
        error_message: row.errorMessage ?? row.error ?? null,
        in_progress: ['running', 'pending', 'building', 'queued'].includes(status),
    };
}

function deployRollup(entityStatus, lastDeployment) {
    const entity = String(entityStatus ?? '').toLowerCase();
    const deploy = String(lastDeployment?.status ?? '').toLowerCase();

    if (entity === 'error' || deploy === 'error') {
        return 'error';
    }

    if (lastDeployment?.in_progress || ['running', 'pending', 'building'].includes(entity)) {
        return 'in_progress';
    }

    if (['done', 'idle', ''].includes(entity) && ['done', 'success', ''].includes(deploy)) {
        return 'ready';
    }

    return entity || deploy || 'unknown';
}

async function latestDeploymentFromRows(api, rowsPromise) {
    const rows = await rowsPromise;
    rows.sort((a, b) => String(b.createdAt ?? b.startedAt ?? '').localeCompare(String(a.createdAt ?? a.startedAt ?? '')));

    return rows[0] ? summarizeDeployment(rows[0]) : null;
}

export async function listContainers(api, name) {
    const stack = await findStack(api, name);
    let raw = [];

    try {
        raw = await api.containersByAppName(stack.appName);
    } catch {
        raw = [];
    }

    if (raw.length === 0 && stack.appName !== stack.name) {
        try {
            raw = await api.containersByAppName(stack.name);
        } catch {
            raw = [];
        }
    }

    return {
        name: stack.name,
        compose_id: stack.composeId,
        app_name: stack.appName,
        containers: normalizeContainers(raw),
    };
}

export async function listDeployments(api, name) {
    const stack = await findStack(api, name);
    const rows = await api.composeDeployments(stack.composeId);
    rows.sort((a, b) => String(b.createdAt ?? '').localeCompare(String(a.createdAt ?? '')));

    return {
        name: stack.name,
        compose_id: stack.composeId,
        compose_status: stack.compose.composeStatus ?? stack.compose.status ?? null,
        deployments: rows.slice(0, 15).map(summarizeDeployment),
    };
}

export async function listDomains(api, name) {
    const stack = await findStack(api, name);

    return {
        name: stack.name,
        compose_id: stack.composeId,
        domains: (await api.domainsByCompose(stack.composeId)).map(summarizeDomain),
    };
}

export async function listServices(api, name) {
    const stack = await findStack(api, name);

    return {
        name: stack.name,
        compose_id: stack.composeId,
        services: await api.loadServices(stack.composeId),
    };
}

export async function listMounts(api, { name, service = null }) {
    const stack = await findStack(api, name);
    const services = service
        ? [service]
        : await api.loadServices(stack.composeId);
    const mounts = [];

    for (const item of services) {
        try {
            const rows = await api.loadMountsByService(stack.composeId, item);
            mounts.push({ service: item, mounts: rows.map(summarizeMount) });
        } catch (error) {
            mounts.push({ service: item, mounts: [], error: error.message });
        }
    }

    return {
        name: stack.name,
        compose_id: stack.composeId,
        volumes: mounts,
    };
}

export async function listApplications(api) {
    const projects = await api.allProjects();
    const applications = [];

    for (const project of projects) {
        const projectId = String(project.projectId ?? project.id ?? '');
        const projectName = String(project.name ?? '');

        if (projectId === '') {
            continue;
        }

        let full;

        try {
            full = await api.getProject(projectId);
        } catch {
            continue;
        }

        const environments = full.environments ?? getPath(full, 'result.data.environments') ?? [];

        if (!Array.isArray(environments)) {
            continue;
        }

        for (const environment of environments) {
            const apps = environment?.applications ?? [];

            if (!Array.isArray(apps)) {
                continue;
            }

            for (const application of apps) {
                applications.push({
                    project: projectName,
                    environment: environment.name ?? null,
                    name: application.name ?? null,
                    app_name: application.appName ?? null,
                    application_id: application.applicationId ?? application.id ?? null,
                    status: application.applicationStatus ?? application.status ?? null,
                });
            }
        }
    }

    return applications;
}

export async function readApplicationLogs(api, { name, applicationId = null, tail = 300 }) {
    const app = await findApplication(api, { name, applicationId });

    let domains = [];

    try {
        domains = (await api.domainsByApplication(app.application_id)).map(summarizeDomain);
    } catch {
        domains = [];
    }

    return {
        ...app,
        domains,
        body: await api.readApplicationLogs(app.application_id, tail),
    };
}

export async function getComposeEnvSummary(api, { name = null, composeId = null }) {
    let stack;

    if (composeId) {
        const compose = await api.getCompose(composeId);
        stack = {
            name: compose.name ?? compose.appName ?? null,
            composeId: composeId,
            compose,
            appName: String(compose.appName ?? compose.name ?? ''),
        };
    } else {
        if (!name) {
            throw new Error('Specifica name (progetto compose) o composeId.');
        }

        stack = await findStack(api, name);
    }

    const envText = String(stack.compose.env ?? stack.compose.environment ?? '');

    return {
        name: stack.name,
        compose_id: stack.composeId,
        app_name: stack.appName,
        compose_status: stack.compose.composeStatus ?? stack.compose.status ?? null,
        source_type: stack.compose.sourceType ?? null,
        env: summarizeEnvForMcp(envText),
    };
}

export async function getComposeDeployStatus(api, { name = null, composeId = null }) {
    let targetComposeId = composeId;
    let stackName = name ?? null;
    let compose = null;

    if (!targetComposeId) {
        if (!name) {
            throw new Error('Specifica name (progetto compose) o composeId.');
        }

        const stack = await findStack(api, name);
        targetComposeId = stack.composeId;
        stackName = stack.name;
        compose = stack.compose;
    } else {
        compose = await api.getCompose(targetComposeId);
    }

    const composeStatus = compose.composeStatus ?? compose.status ?? null;
    const lastDeployment = await latestDeploymentFromRows(api, api.composeDeployments(targetComposeId));

    return {
        name: stackName,
        compose_id: targetComposeId,
        compose_status: composeStatus,
        last_deployment: lastDeployment,
        rollup: deployRollup(composeStatus, lastDeployment),
    };
}

export async function getApplicationDeployStatus(api, { name = null, applicationId = null }) {
    const listed = await findApplication(api, { name, applicationId });
    const full = await api.getApplication(listed.application_id);
    const applicationStatus = full.applicationStatus ?? full.status ?? listed.status ?? null;
    const lastDeployment = await latestDeploymentFromRows(
        api,
        api.applicationDeployments(listed.application_id),
    );
    const appName = String(full.appName ?? full.name ?? listed.app_name ?? listed.name ?? '');
    const containers = normalizeContainers(await api.applicationContainers(appName));
    const running = containers.some((row) => row.state === 'running');

    return {
        name: listed.name,
        application_id: listed.application_id,
        app_name: appName || null,
        application_status: applicationStatus,
        last_deployment: lastDeployment,
        rollup: deployRollup(applicationStatus, lastDeployment),
        container_running: running,
        containers,
    };
}

export async function listApplicationContainers(api, { name = null, applicationId = null }) {
    const listed = await findApplication(api, { name, applicationId });
    const full = await api.getApplication(listed.application_id);
    const appName = String(full.appName ?? full.name ?? listed.app_name ?? listed.name ?? '');

    if (appName === '') {
        throw new Error(`Applicazione ${listed.application_id} senza appName su Dokploy.`);
    }

    const containers = normalizeContainers(await api.applicationContainers(appName));

    return {
        name: listed.name,
        application_id: listed.application_id,
        app_name: appName,
        containers,
    };
}

export async function restartApplicationContainer(
    api,
    { name = null, applicationId = null, containerId = null },
) {
    const listed = await listApplicationContainers(api, { name, applicationId });
    let target = null;

    if (containerId) {
        target = listed.containers.find((row) => row.id === containerId) ?? null;

        if (!target) {
            throw new Error(`Container ${containerId} non trovato per «${listed.name}».`);
        }
    } else {
        target =
            listed.containers.find((row) => row.state === 'running')
            ?? listed.containers.find((row) => row.id)
            ?? null;
    }

    if (!target?.id) {
        throw new Error(`Nessun container riavviabile per «${listed.name}».`);
    }

    const result = await api.restartContainer(target.id);

    return {
        name: listed.name,
        application_id: listed.application_id,
        container: target,
        restart: result,
    };
}

export async function getApplicationSummary(api, { name = null, applicationId = null }) {
    const listed = await findApplication(api, { name, applicationId });
    const full = await api.getApplication(listed.application_id);
    const envText = String(full.env ?? full.environment ?? '');
    let domains = [];

    try {
        domains = (await api.domainsByApplication(listed.application_id)).map(summarizeDomain);
    } catch {
        domains = [];
    }

    return {
        project: listed.project,
        environment: listed.environment,
        name: listed.name,
        app_name: listed.app_name,
        application_id: listed.application_id,
        status: full.applicationStatus ?? full.status ?? listed.status ?? null,
        source_type: full.sourceType ?? null,
        domains,
        env: summarizeEnvForMcp(envText),
    };
}

export async function deployComposeStack(api, { name = null, composeId = null }) {
    let targetComposeId = composeId;

    if (!targetComposeId) {
        if (!name) {
            throw new Error('Specifica name (progetto compose) o composeId.');
        }

        const stack = await findStack(api, name);
        targetComposeId = stack.composeId;
    }

    const result = await api.deployCompose(targetComposeId);

    return {
        compose_id: targetComposeId,
        name: name ?? null,
        deploy: result,
    };
}

export async function deployApplicationByRef(api, { name = null, applicationId = null }) {
    const app = await findApplication(api, { name, applicationId });
    const result = await api.deployApplication(app.application_id);

    return {
        ...app,
        deploy: result,
    };
}

export async function patchApplicationEnv(
    api,
    { name = null, applicationId = null, updates, redeploy = true },
) {
    if (!updates || typeof updates !== 'object' || Array.isArray(updates)) {
        throw new Error('updates deve essere un oggetto chiave → valore.');
    }

    const keys = Object.keys(updates);

    if (keys.length === 0) {
        throw new Error('updates vuoto.');
    }

    const app = await findApplication(api, { name, applicationId });
    const full = await api.getApplication(app.application_id);
    const envMap = parseEnvLines(String(full.env ?? full.environment ?? ''));

    for (const key of keys) {
        envMap.set(key, String(updates[key] ?? ''));
    }

    const envText = serializeEnvLines(envMap);
    const save = await api.saveApplicationEnvironment(app.application_id, envText);
    let deploy = null;

    if (redeploy) {
        deploy = await api.deployApplication(app.application_id);
    }

    return {
        application_id: app.application_id,
        name: app.name,
        updated_keys: keys.sort(),
        env_after: summarizeEnvForMcp(envText),
        save,
        deploy,
    };
}

export async function restartStackService(api, { name, service }) {
    const listed = await listContainers(api, name);
    const container = listed.containers.find((row) => row.service === service);

    if (!container?.id) {
        throw new Error(`Servizio «${service}» senza containerId nello stack «${name}».`);
    }

    const result = await api.restartContainer(container.id);

    return {
        name,
        service,
        container_id: container.id,
        restart: result,
    };
}

export async function getServerHealth(api) {
    return await api.getServerHealth();
}

export async function inspectStack(api, { name, tail = 300, all = false, services = null }) {
    const listed = await listContainers(api, name);
    const stack = await findStack(api, name);
    const deployments = await api.composeDeployments(stack.composeId);
    deployments.sort((a, b) => String(b.createdAt ?? '').localeCompare(String(a.createdAt ?? '')));
    const last = deployments[0] ?? null;
    const containers = listed.containers;
    const wanted = all ? null : (services?.length ? services : DEFAULT_LOG_SERVICES);
    const logs = [];

    for (const container of containers) {
        if (wanted && (!container.service || !wanted.includes(container.service))) {
            continue;
        }

        if (!container.id) {
            logs.push({ ...container, body: '', error: 'containerId mancante' });
            continue;
        }

        try {
            const body = await api.readComposeLogs(stack.composeId, container.id, tail);
            logs.push({ ...container, body, error: null });
        } catch (error) {
            logs.push({ ...container, body, error: error.message });
        }
    }

    let domains = [];

    try {
        domains = (await api.domainsByCompose(stack.composeId)).map(summarizeDomain);
    } catch {
        domains = [];
    }

    return {
        name: stack.name,
        project_id: stack.projectId,
        compose_id: stack.composeId,
        app_name: stack.appName,
        compose_status: stack.compose.composeStatus ?? stack.compose.status ?? null,
        last_deployment: last ? summarizeDeployment(last) : null,
        domains,
        findings: findings(containers, logs),
        containers,
        logs,
    };
}

export async function readServiceLogs(api, { name, service, tail = 300 }) {
    const report = await inspectStack(api, { name, tail, services: [service] });
    const log = report.logs.find((row) => row.service === service) ?? report.logs[0] ?? null;

    if (!log) {
        throw new Error(`Servizio «${service}» non trovato nello stack «${name}».`);
    }

    return log;
}

export function createApiFromEnv() {
    const root = process.env.DOKHOSTS_ROOT || process.env.CURSOR_PROJECT_DIR;
    const candidates = [
        root ? resolve(root, '.env') : null,
        resolve(process.cwd(), '.env'),
        resolve(repoRoot(), '.env'),
    ];

    for (const path of candidates) {
        loadEnvFile(path);
    }

    return new DokployApi({
        url: process.env.DOKPLOY_URL,
        apiKey: process.env.DOKPLOY_API_KEY,
    });
}