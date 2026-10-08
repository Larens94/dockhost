#!/usr/bin/env node
// server.mjs — DokHosts Dokploy MCP stdio server (inspect + deploy/env actions).
//
// exports: none
// used_by: .cursor/mcp.json
// rules:   write tools (deploy_*, patch_application_env) mutate Dokploy — agent must not log secrets from env
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_mcp_v13 | deploy status, compose env summary, app containers/restart
// message: 

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import {
    createApiFromEnv,
    deployApplicationByRef,
    deployComposeStack,
    getApplicationDeployStatus,
    getApplicationSummary,
    getComposeDeployStatus,
    getComposeEnvSummary,
    getServerHealth,
    listApplicationContainers,
    inspectStack,
    listApplications,
    listContainers,
    listDeployments,
    listDomains,
    listMounts,
    listServices,
    listStacks,
    patchApplicationEnv,
    readApplicationLogs,
    readServiceLogs,
    restartApplicationContainer,
    restartStackService,
} from './client.mjs';

function text(payload) {
    return {
        content: [{ type: 'text', text: JSON.stringify(payload, null, 2) }],
    };
}

function fail(error) {
    return {
        content: [{ type: 'text', text: error instanceof Error ? error.message : String(error) }],
        isError: true,
    };
}

const stackName = z.string().describe('Nome progetto Dokploy, es. infra1 o infra2');
const tail = z.number().int().min(1).max(10000).optional().describe('Righe di log (default 300)');

const server = new McpServer({
    name: 'dokhosts-dokploy',
    version: '1.3.1',
});

const appRef = {
    name: z.string().optional().describe('Nome applicazione Dokploy, es. dokhosts o slug sito'),
    applicationId: z.string().optional().describe('ID Dokploy se il nome non è unico'),
};

function tool(name, description, schema, handler) {
    server.tool(name, description, schema, async (args) => {
        try {
            return text(await handler(createApiFromEnv(), args));
        } catch (error) {
            return fail(error);
        }
    });
}

tool(
    'list_stacks',
    'Elenca i progetti Compose su Dokploy (infra DokHosts e altri).',
    {},
    (api) => listStacks(api),
);

tool(
    'list_containers',
    'Container Docker di uno stack, senza log (stato, health, servizio).',
    { name: stackName },
    (api, { name }) => listContainers(api, name),
);

tool(
    'list_services',
    'Servizi dichiarati nel compose (mariadb, phpmyadmin, sftp, …).',
    { name: stackName },
    (api, { name }) => listServices(api, name),
);

tool(
    'list_domains',
    'Domini Traefik dello stack (phpMyAdmin, pgAdmin, MinIO). Utile per HTTPS / host PMA.',
    { name: stackName },
    (api, { name }) => listDomains(api, name),
);

tool(
    'list_deployments',
    'Storico deploy compose (status, errore).',
    { name: stackName },
    (api, { name }) => listDeployments(api, name),
);

tool(
    'list_mounts',
    'Volumi Docker montati sui servizi. Per MariaDB: se il Name è di un deploy vecchio, le password MYSQL_* non si applicano.',
    {
        name: stackName,
        service: z.string().optional().describe('Es. mariadb. Se omesso, tutti i servizi.'),
    },
    (api, { name, service }) => listMounts(api, { name, service: service ?? null }),
);

tool(
    'inspect_stack',
    'Diagnosi completa: container, domini, log mysql-grants/mariadb/phpmyadmin, findings.',
    {
        name: stackName,
        tail,
        all: z.boolean().optional().describe('Se true, log di tutti i container'),
    },
    (api, { name, tail, all }) => inspectStack(api, { name, tail: tail ?? 300, all: Boolean(all) }),
);

tool(
    'read_service_logs',
    'Log di un servizio compose (mysql-grants, mariadb, phpmyadmin, postgres, sftp, …).',
    {
        name: stackName,
        service: z.string().describe('Nome servizio compose, es. mysql-grants'),
        tail,
    },
    (api, { name, service, tail }) => readServiceLogs(api, { name, service, tail: tail ?? 300 }),
);

tool(
    'list_applications',
    'Applicazioni Dokploy (es. pannello dokhosts, siti Laravel Toolkit), non gli stack compose.',
    {},
    (api) => listApplications(api),
);

tool(
    'read_application_logs',
    'Log di un’applicazione Dokploy (pannello o sito GitLab), non di un compose infra.',
    {
        name: z.string().optional().describe('Nome app, es. dokhosts'),
        applicationId: z.string().optional().describe('ID Dokploy se il nome non è unico'),
        tail,
    },
    (api, { name, applicationId, tail }) =>
        readApplicationLogs(api, { name, applicationId: applicationId ?? null, tail: tail ?? 300 }),
);

tool(
    'get_server_health',
    'Salute del server Docker Dokploy (RAM, disco, numero container).',
    {},
    (api) => getServerHealth(api),
);

tool(
    'get_application',
    'Dettaglio applicazione (domini, status, env con password/chiavi mascherate).',
    appRef,
    (api, args) => getApplicationSummary(api, args),
);

tool(
    'get_compose_env_summary',
    'Env del compose infra (MYSQL_* ecc. mascherati) + compose_status — senza YAML composeFile.',
    {
        name: stackName.optional(),
        composeId: z.string().optional().describe('ID compose se già noto'),
    },
    (api, { name, composeId }) => getComposeEnvSummary(api, { name: name ?? null, composeId: composeId ?? null }),
);

tool(
    'get_compose_deploy_status',
    'Ultimo deploy compose + compose_status + rollup ready|in_progress|error (dopo deploy_compose).',
    {
        name: stackName.optional(),
        composeId: z.string().optional().describe('ID compose se già noto'),
    },
    (api, { name, composeId }) => getComposeDeployStatus(api, { name: name ?? null, composeId: composeId ?? null }),
);

tool(
    'get_application_deploy_status',
    'Ultimo deploy applicazione + application_status + container_running + rollup.',
    appRef,
    (api, args) => getApplicationDeployStatus(api, args),
);

tool(
    'list_application_containers',
    'Container Docker dell’applicazione (Nixpacks/Git), non dello stack compose infra.',
    appRef,
    (api, args) => listApplicationContainers(api, args),
);

tool(
    'deploy_compose',
    'Avvia deploy di uno stack Compose (es. infra1). Riavvia i servizi del compose — non modifica il YAML.',
    {
        name: stackName.optional(),
        composeId: z.string().optional().describe('ID compose se già noto'),
    },
    (api, { name, composeId }) => deployComposeStack(api, { name: name ?? null, composeId: composeId ?? null }),
);

tool(
    'deploy_application',
    'Build/deploy di un’applicazione Dokploy (sito Laravel o pannello). Equivalente al pulsante Deploy su Dokploy.',
    appRef,
    (api, args) => deployApplicationByRef(api, args),
);

tool(
    'patch_application_env',
    'Aggiorna variabili env su Dokploy (merge sulle chiavi indicate) e opzionalmente redeploy. Valori sensibili non sono restituiti in chiaro.',
    {
        ...appRef,
        updates: z
            .record(z.string(), z.string())
            .describe('Es. APP_URL, ASSET_URL, TRUSTED_PROXIES, APP_ENV — una chiave per riga logica'),
        redeploy: z
            .boolean()
            .optional()
            .describe('Se true (default), application.deploy dopo saveEnvironment'),
    },
    (api, { name, applicationId, updates, redeploy }) =>
        patchApplicationEnv(api, {
            name: name ?? null,
            applicationId: applicationId ?? null,
            updates,
            redeploy: redeploy !== false,
        }),
);

tool(
    'restart_stack_service',
    'Riavvia un container di uno stack compose (es. sftp su infra1) via docker.restartContainer.',
    {
        name: stackName,
        service: z.string().describe('Servizio compose, es. sftp o mariadb'),
    },
    (api, { name, service }) => restartStackService(api, { name, service }),
);

tool(
    'restart_application_container',
    'Riavvia il container runtime di un’applicazione Dokploy (default: primo in running).',
    {
        ...appRef,
        containerId: z.string().optional().describe('ID container se ce n’è più di uno'),
    },
    (api, { name, applicationId, containerId }) =>
        restartApplicationContainer(api, {
            name: name ?? null,
            applicationId: applicationId ?? null,
            containerId: containerId ?? null,
        }),
);

const transport = new StdioServerTransport();
await server.connect(transport);
