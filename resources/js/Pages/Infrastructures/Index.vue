<!-- Index.vue — Infrastructures index component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Panel infrastructure management. One infra = one Dokploy compose.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';
import InfrastructureStatusBadge from '../../Components/InfrastructureStatusBadge.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    infrastructures: {
        type: Array,
        required: true,
    },
});

const search = ref('');

const filteredInfrastructures = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.infrastructures;
    }

    return props.infrastructures.filter((infra) => {
        const slugMatch = infra.slug && infra.slug.toLowerCase().includes(q);
        const nameMatch = infra.name && infra.name.toLowerCase().includes(q);
        const hostMatch = infra.mysql_host && infra.mysql_host.toLowerCase().includes(q);

        return slugMatch || nameMatch || hostMatch;
    });
});

const readyCount = computed(() =>
    props.infrastructures.filter((i) => i.status === 'ready' || i.status === 'deployed').length,
);
</script>

<template>
    <AppLayout :title="t('infrastructures.title')" :description="t('infrastructures.description')" :full-width="true">
        <template #actions>
            <Link
                href="/infrastructures/create"
                class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('infrastructures.new') }}</span>
            </Link>
        </template>

        <!-- Metric Stat Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <!-- Total Infrastructures -->
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z"
                        />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('infrastructures.title') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ infrastructures.length }}</p>
                </div>
            </div>

            <!-- Ready / Deployed Count -->
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Active & Ready</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ readyCount }}</p>
                </div>
            </div>
        </div>

        <!-- Empty Global State -->
        <div
            v-if="infrastructures.length === 0"
            class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z"
                    />
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('infrastructures.empty_title') }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ t('infrastructures.empty_body') }}</p>
            <Link
                href="/infrastructures/create"
                class="mt-5 inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800 transition"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('infrastructures.new') }}</span>
            </Link>
        </div>

        <!-- Table Container with Search -->
        <div v-else class="space-y-4">
            <!-- Search & Filter Bar -->
            <div class="flex items-center justify-between gap-4">
                <div class="relative w-full max-w-sm">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search infrastructures by slug, name, or host..."
                        class="w-full rounded-xl border border-neutral-200 bg-white py-2 pl-9 pr-8 text-xs text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs"
                    />
                    <button
                        v-if="search"
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-700"
                        @click="search = ''"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <p class="text-xs text-neutral-500 hidden sm:block">
                    {{ filteredInfrastructures.length }} {{ filteredInfrastructures.length === 1 ? 'infra' : 'infras' }}
                </p>
            </div>

            <!-- Table or No-Matches State -->
            <div v-if="filteredInfrastructures.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center text-xs text-neutral-500">
                No infrastructures match your search query.
            </div>

            <ResponsiveTable v-else>
                <template #table>
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('common.slug') }}</th>
                            <th class="px-5 py-3.5">{{ t('infrastructures.name') }}</th>
                            <th class="px-5 py-3.5">{{ t('infrastructures.state') }}</th>
                            <th class="px-5 py-3.5">MySQL</th>
                            <th class="px-5 py-3.5">{{ t('common.sftp') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="infra in filteredInfrastructures" :key="infra.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link :href="`/infrastructures/${infra.id}`" class="font-semibold text-neutral-900 group-hover:underline flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700 group-hover:bg-neutral-900 group-hover:text-white transition">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                                        </svg>
                                    </span>
                                    <span>{{ infra.slug }}</span>
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">{{ infra.name || '—' }}</td>
                            <td class="px-5 py-3.5">
                                <InfrastructureStatusBadge :status="infra.status" />
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-500 font-mono">{{ infra.mysql_host }}</td>
                            <td class="px-5 py-3.5 text-xs text-neutral-500 font-mono">
                                {{ infra.sftp_public_host || infra.sftp_host }}{{ infra.sftp_host_port ? ':' + infra.sftp_host_port : '' }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a
                                        v-if="infra.dokploy_project_url"
                                        :href="infra.dokploy_project_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                                    >
                                        Dokploy ↗
                                    </a>
                                    <Link
                                        :href="`/infrastructures/${infra.id}`"
                                        class="rounded-lg px-2.5 py-1 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                                    >
                                        Details
                                    </Link>
                                    <Link
                                        :href="`/infrastructures/${infra.id}?tab=pericolo`"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 transition"
                                    >
                                        {{ t('common.delete') }}
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template #cards>
                    <article
                        v-for="infra in filteredInfrastructures"
                        :key="infra.id"
                        class="rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <Link :href="`/infrastructures/${infra.id}`" class="font-semibold text-neutral-900 hover:underline">
                                {{ infra.slug }}
                            </Link>
                            <InfrastructureStatusBadge :status="infra.status" />
                        </div>
                        <p v-if="infra.name" class="mt-1 text-xs text-neutral-500">{{ infra.name }}</p>
                        <dl class="mt-3 space-y-1.5 text-xs border-t border-neutral-100 pt-3">
                            <div class="flex justify-between gap-2 text-neutral-600">
                                <dt class="text-neutral-400">MySQL</dt>
                                <dd class="font-mono">{{ infra.mysql_host }}</dd>
                            </div>
                            <div class="flex justify-between gap-2 text-neutral-600">
                                <dt class="text-neutral-400">SFTP</dt>
                                <dd class="font-mono truncate">
                                    {{ infra.sftp_public_host || infra.sftp_host }}{{ infra.sftp_host_port ? ':' + infra.sftp_host_port : '' }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-3 flex items-center justify-end gap-2 pt-3 border-t border-neutral-100">
                            <a
                                v-if="infra.dokploy_project_url"
                                :href="infra.dokploy_project_url"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-semibold text-neutral-600 hover:underline"
                            >
                                Dokploy ↗
                            </a>
                            <Link
                                :href="`/infrastructures/${infra.id}`"
                                class="text-xs font-semibold text-neutral-900 hover:underline ml-2"
                            >
                                Details →
                            </Link>
                        </div>
                    </article>
                </template>
            </ResponsiveTable>
        </div>
    </AppLayout>
</template>
