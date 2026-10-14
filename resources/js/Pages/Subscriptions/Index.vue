<!-- Index.vue — Spaces index component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Delete opens DeleteSpaceModal; the typed confirmation must match deletion_confirmation.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Delete action on the spaces list.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Index chrome uses panel translations.
  agent:   antigravity | 2026-10-14 | modern clean user-friendly redesign
-->

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';
import DeleteSpaceModal from '../../Components/DeleteSpaceModal.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    subscriptions: {
        type: Array,
        required: true,
    },
});

const search = ref('');
const spaceToDelete = ref(null);

const filteredSubscriptions = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.subscriptions;
    }

    return props.subscriptions.filter((space) => {
        const nameMatch = space.name && space.name.toLowerCase().includes(q);
        const customerMatch = space.customer?.name && space.customer.name.toLowerCase().includes(q);
        const planMatch = space.service_plan?.name && space.service_plan.name.toLowerCase().includes(q);

        return nameMatch || customerMatch || planMatch;
    });
});

const totalDomains = computed(() =>
    props.subscriptions.reduce((sum, s) => sum + (s.domains_count || 0), 0),
);

const uniqueCustomersCount = computed(() => {
    const ids = new Set(props.subscriptions.map((s) => s.customer_id).filter(Boolean));
    return ids.size;
});
</script>

<template>
    <AppLayout :title="t('spaces.title')" :description="t('spaces.description')" :full-width="true">
        <template #actions>
            <Link
                href="/subscriptions/create"
                class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('spaces.new') }}</span>
            </Link>
        </template>

        <!-- Metric Stat Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Total Spaces -->
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
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('spaces.title') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ subscriptions.length }}</p>
                </div>
            </div>

            <!-- Connected Domains -->
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582"
                        />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('spaces.domains') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ totalDomains }}</p>
                </div>
            </div>

            <!-- Linked Customers -->
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                        />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('spaces.customer') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ uniqueCustomersCount }}</p>
                </div>
            </div>
        </div>

        <!-- Empty Global State -->
        <div
            v-if="subscriptions.length === 0"
            class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('spaces.empty_title') }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ t('spaces.empty_body') }}</p>
            <Link
                href="/subscriptions/create"
                class="mt-5 inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800 transition"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('spaces.new') }}</span>
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
                        placeholder="Search spaces..."
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
                    {{ filteredSubscriptions.length }} {{ filteredSubscriptions.length === 1 ? 'space' : 'spaces' }}
                </p>
            </div>

            <!-- Table or No-Matches State -->
            <div v-if="filteredSubscriptions.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center text-xs text-neutral-500">
                No spaces match your search query.
            </div>

            <ResponsiveTable v-else>
                <template #table>
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('spaces.name') }}</th>
                            <th class="px-5 py-3.5">{{ t('spaces.customer') }}</th>
                            <th class="px-5 py-3.5">{{ t('spaces.plan') }}</th>
                            <th class="px-5 py-3.5 text-center">{{ t('spaces.domains') }}</th>
                            <th class="px-5 py-3.5 text-right">{{ t('spaces.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="space in filteredSubscriptions" :key="space.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link :href="`/subscriptions/${space.id}`" class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700 text-xs font-semibold group-hover:bg-neutral-900 group-hover:text-white transition">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                        </svg>
                                    </span>
                                    <span class="font-semibold text-neutral-900 group-hover:underline">
                                        {{ space.name }}
                                    </span>
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">
                                <Link v-if="space.customer" :href="`/customers/${space.customer.id}`" class="font-medium text-neutral-800 hover:underline">
                                    {{ space.customer.name }}
                                </Link>
                                <span v-else class="text-neutral-300">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-xs">
                                <span v-if="space.service_plan" class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ space.service_plan.name }}
                                </span>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ space.domains_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="`/subscriptions/${space.id}`"
                                        class="rounded-lg px-2.5 py-1 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                                    >
                                        Details
                                    </Link>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50/70 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                        @click="spaceToDelete = space"
                                    >
                                        {{ t('spaces.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </template>

                <template #cards>
                    <article
                        v-for="space in filteredSubscriptions"
                        :key="space.id"
                        class="rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link :href="`/subscriptions/${space.id}`" class="font-semibold text-neutral-900 hover:underline">
                                {{ space.name }}
                            </Link>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-200 bg-rose-50/70 px-2 py-0.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                @click="spaceToDelete = space"
                            >
                                {{ t('spaces.delete') }}
                            </button>
                        </div>
                        <dl class="mt-3 space-y-2 text-xs">
                            <div class="flex justify-between gap-3 text-neutral-600">
                                <dt class="text-neutral-400">{{ t('spaces.customer') }}</dt>
                                <dd class="font-medium text-right">
                                    <Link v-if="space.customer" :href="`/customers/${space.customer.id}`" class="hover:underline">
                                        {{ space.customer.name }}
                                    </Link>
                                    <span v-else>—</span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-3 text-neutral-600">
                                <dt class="text-neutral-400">{{ t('spaces.plan') }}</dt>
                                <dd class="font-medium text-right">{{ space.service_plan?.name || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3 text-neutral-600">
                                <dt class="text-neutral-400">{{ t('spaces.domains') }}</dt>
                                <dd class="font-medium text-right">{{ space.domains_count }}</dd>
                            </div>
                        </dl>
                    </article>
                </template>
            </ResponsiveTable>
        </div>

        <DeleteSpaceModal v-if="spaceToDelete" :space="spaceToDelete" @close="spaceToDelete = null" />
    </AppLayout>
</template>
