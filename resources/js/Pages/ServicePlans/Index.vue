<!-- Index.vue — Plans index component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Catalog of plans to attach to spaces.
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
    plans: {
        type: Array,
        required: true,
    },
});

const search = ref('');

const filteredPlans = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.plans;
    }

    return props.plans.filter((plan) => {
        const nameMatch = plan.name && plan.name.toLowerCase().includes(q);
        const slugMatch = plan.slug && plan.slug.toLowerCase().includes(q);

        return nameMatch || slugMatch;
    });
});

const totalSubscriptions = computed(() =>
    props.plans.reduce((sum, p) => sum + (p.subscriptions_count || 0), 0),
);
</script>

<template>
    <AppLayout :title="t('plans.title')" :description="t('plans.description')" :full-width="true">
        <template #actions>
            <Link
                href="/service-plans/create"
                class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('plans.new') }}</span>
            </Link>
        </template>

        <!-- Metric Stat Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <!-- Total Plans -->
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122"
                        />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('plans.title') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ plans.length }}</p>
                </div>
            </div>

            <!-- Active Subscriptions using plans -->
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"
                        />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('layout.nav.spaces') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ totalSubscriptions }}</p>
                </div>
            </div>
        </div>

        <!-- Empty Global State -->
        <div
            v-if="plans.length === 0"
            class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122" />
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('plans.empty_title') }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ t('plans.empty_body') }}</p>
            <Link
                href="/service-plans/create"
                class="mt-5 inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800 transition"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('plans.new') }}</span>
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
                        placeholder="Search plans by name or slug..."
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
                    {{ filteredPlans.length }} {{ filteredPlans.length === 1 ? 'plan' : 'plans' }}
                </p>
            </div>

            <!-- Table or No-Matches State -->
            <div v-if="filteredPlans.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center text-xs text-neutral-500">
                No plans match your search query.
            </div>

            <ResponsiveTable v-else>
                <template #table>
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('common.name') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.slug') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.domains') }}</th>
                            <th class="px-5 py-3.5 text-center">{{ t('layout.nav.spaces') }}</th>
                            <th class="px-5 py-3.5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="plan in filteredPlans" :key="plan.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link :href="`/service-plans/${plan.id}`" class="font-semibold text-neutral-900 group-hover:underline flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700 group-hover:bg-neutral-900 group-hover:text-white transition">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-.98.626-1.813 1.5-2.122" />
                                        </svg>
                                    </span>
                                    <span>{{ plan.name }}</span>
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-500 font-mono">{{ plan.slug }}</td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">
                                <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-neutral-700 font-medium">
                                    {{ plan.max_domains ?? t('common.unlimited') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ plan.subscriptions_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    :href="`/service-plans/${plan.id}`"
                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                                >
                                    <span>Details</span>
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
                        v-for="plan in filteredPlans"
                        :key="plan.id"
                        class="rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs"
                    >
                        <Link :href="`/service-plans/${plan.id}`" class="font-semibold text-neutral-900 hover:underline">
                            {{ plan.name }}
                        </Link>
                        <p class="mt-1 font-mono text-xs text-neutral-400">{{ plan.slug }}</p>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-xs border-t border-neutral-100 pt-3">
                            <div>
                                <dt class="text-neutral-400">{{ t('common.domains') }}</dt>
                                <dd class="font-medium text-neutral-800">{{ plan.max_domains ?? t('common.unlimited') }}</dd>
                            </div>
                            <div>
                                <dt class="text-neutral-400">{{ t('layout.nav.spaces') }}</dt>
                                <dd class="font-medium text-neutral-800">{{ plan.subscriptions_count }}</dd>
                            </div>
                        </dl>
                    </article>
                </template>
            </ResponsiveTable>
        </div>
    </AppLayout>
</template>
