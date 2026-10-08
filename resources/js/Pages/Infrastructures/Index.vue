<!-- Index.vue — Index component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import InfrastructureStatusBadge from '../../Components/InfrastructureStatusBadge.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

defineProps({
    infrastructures: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        title="Infrastrutture"
        description="Ogni infrastruttura è un progetto Dokploy e uno stack Compose con MariaDB, Postgres e SFTP."
    >
        <template #actions>
            <Link
                href="/infrastructures/create"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                Nuova infrastruttura
            </Link>
        </template>

        <div
            v-if="infrastructures.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">Nessuna infrastruttura del pannello</p>
            <p class="mt-1 text-sm text-zinc-500">
                Crea infra1: nasce un progetto Dokploy a parte, non dentro il progetto del pannello.
            </p>
            <Link
                href="/infrastructures/create"
                class="mt-4 inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white"
            >
                Nuova infrastruttura
            </Link>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">Nome</th>
                        <th class="px-4 py-3 font-medium">Stato</th>
                        <th class="px-4 py-3 font-medium">MySQL</th>
                        <th class="px-4 py-3 font-medium">SFTP</th>
                        <th class="px-4 py-3 font-medium text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="infra in infrastructures" :key="infra.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link :href="`/infrastructures/${infra.id}`" class="font-medium text-zinc-900 hover:underline">
                                {{ infra.slug }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ infra.name || '—' }}</td>
                        <td class="px-4 py-3">
                            <InfrastructureStatusBadge :status="infra.status" />
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ infra.mysql_host }}</td>
                        <td class="px-4 py-3 text-zinc-500">
                            {{ infra.sftp_public_host || infra.sftp_host
                            }}                            {{ infra.sftp_host_port ? ':' + infra.sftp_host_port : '' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a
                                    v-if="infra.dokploy_project_url"
                                    :href="infra.dokploy_project_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs font-medium text-zinc-600 underline underline-offset-2 hover:text-zinc-900"
                                >
                                    Dokploy
                                </a>
                                <Link
                                    :href="`/infrastructures/${infra.id}?tab=pericolo`"
                                    class="text-xs font-medium text-red-700 hover:underline"
                                >
                                    Elimina
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #cards>
                <article
                    v-for="infra in infrastructures"
                    :key="infra.id"
                    class="min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link :href="`/infrastructures/${infra.id}`" class="font-semibold hover:underline">
                                {{ infra.slug }}
                            </Link>
                            <p v-if="infra.name" class="text-sm break-words text-zinc-500">{{ infra.name }}</p>
                        </div>
                        <InfrastructureStatusBadge :status="infra.status" />
                    </div>

                    <dl class="mt-3 grid gap-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">MySQL</dt>
                            <dd class="font-medium text-right break-all">{{ infra.mysql_host }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">Postgres</dt>
                            <dd class="font-medium text-right break-all">{{ infra.postgres_host }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">SFTP</dt>
                            <dd class="font-medium text-right break-all">
                                {{ infra.sftp_public_host || infra.sftp_host
                                }}{{ infra.sftp_host_port ? ':' + infra.sftp_host_port : '' }}
                            </dd>
                        </div>
                    </dl>

                    <p
                        v-if="infra.last_error"
                        class="mt-3 min-w-0 text-sm break-words text-red-700 [overflow-wrap:anywhere]"
                    >
                        {{ infra.last_error }}
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            :href="`/infrastructures/${infra.id}`"
                            class="rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-medium hover:bg-zinc-50"
                        >
                            Apri
                        </Link>
                        <a
                            v-if="infra.dokploy_project_url"
                            :href="infra.dokploy_project_url"
                            target="_blank"
                            rel="noopener"
                            class="rounded-lg border border-neutral-200 px-3 py-1.5 text-sm font-medium hover:bg-zinc-50"
                        >
                            Dokploy
                        </a>
                    </div>
                </article>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
