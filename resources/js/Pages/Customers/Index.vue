<!-- Index.vue — Customer index component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Panel anagrafica list with spaces and domains counts.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Index chrome uses panel translations.
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
    customers: {
        type: Array,
        required: true,
    },
});

const search = ref('');

const filteredCustomers = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.customers;
    }

    return props.customers.filter((customer) => {
        const nameMatch = customer.name && customer.name.toLowerCase().includes(q);
        const emailMatch = customer.email && customer.email.toLowerCase().includes(q);

        return nameMatch || emailMatch;
    });
});

const domainCount = computed(() =>
    props.customers.reduce((total, customer) => total + (customer.domains_count || 0), 0),
);

const spazioCount = computed(() =>
    props.customers.reduce((total, customer) => total + (customer.subscriptions_count || 0), 0),
);

const getInitials = (name) => {
    if (!name) {
        return 'DH';
    }

    const parts = name.trim().split(/[\s@._-]+/).filter(Boolean);
    if (parts.length === 1) {
        return parts[0].substring(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[1][0]).toUpperCase();
};
</script>

<template>
    <AppLayout :title="t('customers.title')" :description="t('customers.description')" :full-width="true">
        <template #actions>
            <Link
                href="/customers/create"
                class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('customers.new') }}</span>
            </Link>
        </template>

        <!-- Metric Stat Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Customers Count -->
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
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('customers.title') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ customers.length }}</p>
                </div>
            </div>

            <!-- Spaces Count -->
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
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('customers.spaces') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ spazioCount }}</p>
                </div>
            </div>

            <!-- Domains Count -->
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
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('customers.domains') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ domainCount }}</p>
                </div>
            </div>
        </div>

        <!-- Empty Global State -->
        <div
            v-if="customers.length === 0"
            class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                    />
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('customers.empty_title') }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ t('customers.empty_body') }}</p>
            <Link
                href="/customers/create"
                class="mt-5 inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800 transition"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                <span>{{ t('customers.new') }}</span>
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
                        placeholder="Search customers..."
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
                    {{ filteredCustomers.length }} {{ filteredCustomers.length === 1 ? 'customer' : 'customers' }}
                </p>
            </div>

            <!-- Table or No-Matches State -->
            <div v-if="filteredCustomers.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center text-xs text-neutral-500">
                No customers match your search query.
            </div>

            <ResponsiveTable v-else>
                <template #table>
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('customers.name') }}</th>
                            <th class="px-5 py-3.5">{{ t('customers.email') }}</th>
                            <th class="px-5 py-3.5 text-center">{{ t('customers.spaces') }}</th>
                            <th class="px-5 py-3.5 text-center">{{ t('customers.domains') }}</th>
                            <th class="px-5 py-3.5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="customer in filteredCustomers" :key="customer.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link :href="`/customers/${customer.id}`" class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-900 text-[11px] font-semibold text-white shadow-2xs group-hover:scale-105 transition-transform">
                                        {{ getInitials(customer.name) }}
                                    </span>
                                    <span class="font-semibold text-neutral-900 group-hover:text-black">
                                        {{ customer.name }}
                                    </span>
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-500">
                                <span v-if="customer.email" class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                    </svg>
                                    {{ customer.email }}
                                </span>
                                <span v-else class="text-neutral-300">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ customer.subscriptions_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ customer.domains_count }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    :href="`/customers/${customer.id}`"
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
                        v-for="customer in filteredCustomers"
                        :key="customer.id"
                        class="rounded-2xl border border-neutral-200/80 bg-white p-4 shadow-xs"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <Link :href="`/customers/${customer.id}`" class="flex items-center gap-2.5 min-w-0">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-neutral-900 text-[11px] font-semibold text-white shadow-2xs">
                                    {{ getInitials(customer.name) }}
                                </span>
                                <span class="font-semibold text-neutral-900 truncate">{{ customer.name }}</span>
                            </Link>

                            <Link
                                :href="`/customers/${customer.id}`"
                                class="text-xs font-semibold text-neutral-600 hover:text-neutral-900"
                            >
                                →
                            </Link>
                        </div>

                        <p v-if="customer.email" class="mt-2 text-xs text-neutral-500 truncate flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 shrink-0 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <span>{{ customer.email }}</span>
                        </p>

                        <div class="mt-3 flex items-center gap-3 pt-3 border-t border-neutral-100 text-xs text-neutral-600">
                            <span><strong>{{ customer.subscriptions_count }}</strong> spaces</span>
                            <span class="text-neutral-300">·</span>
                            <span><strong>{{ customer.domains_count }}</strong> domains</span>
                        </div>
                    </article>
                </template>
            </ResponsiveTable>
        </div>
    </AppLayout>
</template>
