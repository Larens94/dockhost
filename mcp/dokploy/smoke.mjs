#!/usr/bin/env node
// smoke.mjs — smoke module.
//
// exports: none
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 


import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport, getDefaultEnvironment } from '@modelcontextprotocol/sdk/client/stdio.js';
import { fileURLToPath } from 'node:url';
import { repoRoot } from './client.mjs';

function parseToolText(result) {
    const text = result.content?.find((item) => item.type === 'text')?.text ?? '';

    if (result.isError) {
        throw new Error(text || 'tool error');
    }

    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}

const env = { ...getDefaultEnvironment() };

for (const [key, value] of Object.entries(process.env)) {
    if (typeof value === 'string') {
        env[key] = value;
    }
}

const transport = new StdioClientTransport({
    command: process.execPath,
    args: [fileURLToPath(new URL('./server.mjs', import.meta.url))],
    cwd: repoRoot(),
    env,
    stderr: 'pipe',
});

const client = new Client({ name: 'dokhosts-dokploy-smoke', version: '1.0.0' });

try {
    await client.connect(transport);

    const { tools } = await client.listTools();
    const names = tools.map((tool) => tool.name).sort();
    const expected = [
        'deploy_application',
        'deploy_compose',
        'get_application',
        'get_application_deploy_status',
        'get_compose_deploy_status',
        'get_compose_env_summary',
        'get_server_health',
        'inspect_stack',
        'list_application_containers',
        'list_applications',
        'list_containers',
        'list_deployments',
        'list_domains',
        'list_mounts',
        'list_services',
        'list_stacks',
        'patch_application_env',
        'read_application_logs',
        'read_service_logs',
        'restart_application_container',
        'restart_stack_service',
    ];

    for (const name of expected) {
        if (!names.includes(name)) {
            throw new Error(`tool mancante: ${name} (visto: ${names.join(', ')})`);
        }
    }

    const stacks = parseToolText(await client.callTool({ name: 'list_stacks', arguments: {} }));

    if (!Array.isArray(stacks) || stacks.length === 0) {
        throw new Error('list_stacks vuoto');
    }

    const infra = stacks.find((stack) => stack.name === 'infra1');

    if (!infra?.composeIds?.length) {
        throw new Error('infra1 assente da list_stacks');
    }

    const report = parseToolText(
        await client.callTool({
            name: 'inspect_stack',
            arguments: { name: 'infra1', tail: 40 },
        }),
    );
    const grants = parseToolText(
        await client.callTool({
            name: 'read_service_logs',
            arguments: { name: 'infra1', service: 'mysql-grants', tail: 40 },
        }),
    );
    const domains = parseToolText(await client.callTool({ name: 'list_domains', arguments: { name: 'infra1' } }));
    const apps = parseToolText(await client.callTool({ name: 'list_applications', arguments: {} }));
    const mounts = parseToolText(
        await client.callTool({ name: 'list_mounts', arguments: { name: 'infra1', service: 'mariadb' } }),
    );

    const findings = (report.findings ?? []).map((finding) => finding.code);

    console.log('mcp_ok tools=' + names.join(','));
    console.log('stacks=' + stacks.map((stack) => stack.name).join(','));
    console.log('infra1_findings=' + findings.join(','));
    console.log('grants_state=' + (grants.state ?? '') + ' ' + (grants.status ?? ''));
    console.log('grants_excerpt=' + String(grants.body ?? '').slice(0, 180).replaceAll('\n', ' '));
    console.log('pma_host=' + (domains.domains?.[0]?.host ?? ''));
    console.log('apps=' + apps.map((app) => app.name).join(','));
    console.log('mariadb_volume=' + (mounts.volumes?.[0]?.mounts?.[0]?.name ?? ''));
} finally {
    await client.close();
}