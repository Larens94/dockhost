<!-- Index.vue — Domains index component.

  exports: defineProps
  used_by: DomainController::index
  rules:   Labels come from shared panel translations. Italian is the default locale.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | Member landing page.
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
    domains: {
        type: Array,
        required: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
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
        const stackMatch = (domain.stack_label || domain.stack) && (domain.stack_label || domain.stack).toLowerCase().includes(q);

        return fqdnMatch || customerMatch || stackMatch;
    });
});
</script>

<template>
    <AppLayout
        :title="isAdmin ? t('domains.title') : t('domains.my_hosting_title')"
        :description="isAdmin ? t('domains.description_admin') : t('domains.description_member')"
        :full-width="true"
    >
        <!-- Empty Global State -->
        <div
            v-if="domains.length === 0"
            class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                </svg>
            </div>
            <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('domains.empty_title') }}</p>
            <p v-if="!isAdmin" class="mt-1 text-xs text-neutral-500">
                {{ t('domains.empty_member') }}
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
                        placeholder="Search domains by FQDN, customer, stack..."
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
                    {{ filteredDomains.length }} {{ filteredDomains.length === 1 ? 'domain' : 'domains' }}
                </p>
            </div>

            <!-- Table or No-Matches State -->
            <div v-if="filteredDomains.length === 0" class="rounded-2xl border border-neutral-200 bg-white p-10 text-center text-xs text-neutral-500">
                No domains match your search query.
            </div>

            <ResponsiveTable v-else>
                <template #table>
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('domains.domain') }}</th>
                            <th class="px-5 py-3.5">{{ t('domains.customer') }}</th>
                            <th class="px-5 py-3.5">{{ t('domains.stack') }}</th>
                            <th class="px-5 py-3.5 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="domain in filteredDomains" :key="domain.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link :href="`/domains/${domain.id}`" class="font-semibold text-neutral-900 group-hover:underline flex items-center gap-2">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700 group-hover:bg-neutral-900 group-hover:text-white transition">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                                        </svg>
                                    </span>
                                    <span>{{ domain.fqdn }}</span>
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">
                                <Link v-if="domain.customer" :href="`/customers/${domain.customer.id}`" class="hover:underline font-medium text-neutral-800">
                                    {{ domain.customer.name }}
                                </Link>
                                <span v-else class="text-neutral-300">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">
                                <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-neutral-700 font-medium">
                                    {{ domain.stack_label || domain.stack || '—' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Link
                                    :href="`/domains/${domain.id}`"
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
            </ResponsiveTable>
        </div>
    </AppLayout>
</template>
