<!-- Index.vue — Laravel Toolkit index component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Panel Laravel applications overview.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    domains: {
        type: Array,
        required: true,
    },
});

const search = ref('');

const filteredDomains = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.domains;
    }

    return props.domains.filter((domain) => {
        const fqdnMatch = domain.fqdn && domain.fqdn.toLowerCase().includes(q);
        const customerMatch = domain.customer?.name && domain.customer.name.toLowerCase().includes(q);
        const spaceMatch = domain.subscription?.name && domain.subscription.name.toLowerCase().includes(q);
        const appMatch = domain.dokploy_application?.dokploy_application_id &&
            domain.dokploy_application.dokploy_application_id.toLowerCase().includes(q);

        return fqdnMatch || customerMatch || spaceMatch || appMatch;
    });
});
</script>

<template>
    <AppLayout
        :title="t('toolkit_index.title')"
        :description="t('toolkit_index.description')"
        :full-width="true"
    >
        <!-- Metric Stat Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Laravel Applications</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ domains.length }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Connected FQDNs</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ domains.length }}</p>
                </div>
            </div>
        </div>

        <!-- Empty Global State -->
        <div
            v-if="domains.length === 0"
            class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" />
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('toolkit_index.empty_title') }}</p>
            <p class="mt-1 text-xs text-neutral-500 max-w-md mx-auto">
                {{ t('toolkit_index.empty_body') }}
            </p>
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
                        placeholder="Search by domain, customer, space..."
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
                    {{ filteredDomains.length }} {{ filteredDomains.length === 1 ? 'app' : 'apps' }}
                </p>
            </div>

            <!-- Table or No-Matches State -->
            <div v-if="filteredDomains.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center text-xs text-neutral-500">
                No Laravel applications match your search query.
            </div>

            <ResponsiveTable v-else>
                <template #table>
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('common.domain') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.space') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.customer') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.application') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.source') }}</th>
                            <th class="px-5 py-3.5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="domain in filteredDomains" :key="domain.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="`/domains/${domain.id}?tab=laravel`"
                                    class="font-semibold text-neutral-900 group-hover:underline flex items-center gap-2"
                                >
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700 group-hover:bg-neutral-900 group-hover:text-white transition">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                                        </svg>
                                    </span>
                                    <span>{{ domain.fqdn }}</span>
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                <Link
                                    v-if="domain.subscription_id"
                                    :href="`/subscriptions/${domain.subscription_id}`"
                                    class="font-medium text-neutral-800 hover:underline"
                                >
                                    {{ domain.subscription?.name || t('common.space') }}
                                </Link>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">
                                <Link v-if="domain.customer" :href="`/customers/${domain.customer.id}`" class="hover:underline font-medium text-neutral-800">
                                    {{ domain.customer.name }}
                                </Link>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-500 font-mono">
                                <a
                                    v-if="domain.dokploy_application_url"
                                    :href="domain.dokploy_application_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-neutral-900 underline underline-offset-2 hover:text-black"
                                >
                                    {{ domain.dokploy_application?.dokploy_application_id }}
                                </a>
                                <span v-else>{{ domain.dokploy_application?.dokploy_application_id || '—' }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-neutral-700 font-medium">
                                    Dokploy
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    :href="`/domains/${domain.id}?tab=laravel`"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                                >
                                    <span>Toolkit</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template #cards>
                    <article
                        v-for="domain in filteredDomains"
                        :key="domain.id"
                        class="rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link
                                :href="`/domains/${domain.id}?tab=laravel`"
                                class="font-semibold text-neutral-900 hover:underline"
                            >
                                {{ domain.fqdn }}
                            </Link>
                            <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-[10px] font-medium text-neutral-700">
                                Dokploy
                            </span>
                        </div>
                        <dl class="mt-3 space-y-2 text-xs border-t border-neutral-100 pt-3">
                            <div class="flex justify-between gap-3 text-neutral-600">
                                <dt class="text-neutral-400">{{ t('common.space') }}</dt>
                                <dd class="font-medium text-right">
                                    <Link
                                        v-if="domain.subscription_id"
                                        :href="`/subscriptions/${domain.subscription_id}`"
                                        class="hover:underline"
                                    >
                                        {{ domain.subscription?.name || t('common.space') }}
                                    </Link>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3 text-neutral-600">
                                <dt class="text-neutral-400">{{ t('common.customer') }}</dt>
                                <dd class="font-medium text-right">{{ domain.customer?.name || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 text-neutral-600">
                                <dt class="text-neutral-400">{{ t('common.application') }}</dt>
                                <dd class="font-mono text-right truncate">
                                    {{ domain.dokploy_application?.dokploy_application_id || '—' }}
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-3 pt-3 border-t border-neutral-100 text-right">
                            <Link
                                :href="`/domains/${domain.id}?tab=laravel`"
                                class="text-xs font-semibold text-neutral-900 hover:underline"
                            >
                                Open Toolkit →
                            </Link>
                        </div>
                    </article>
                </template>
            </ResponsiveTable>
        </div>
    </AppLayout>
</template>
