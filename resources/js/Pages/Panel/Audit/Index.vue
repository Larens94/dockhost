<!-- Panel/Audit/Index.vue — Admin audit log (latest 100).

  exports: none
  used_by: AuditLogController
  rules:   Admin-only route — no secrets in meta display.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_audit | Audit list UI.
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { usePanelTranslations } from '@/composables/usePanelTranslations';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    logs: {
        type: Array,
        default: () => [],
    },
});

const search = ref('');

const formatMeta = (meta) => {
    if (!meta || typeof meta !== 'object') {
        return '—';
    }

    try {
        return JSON.stringify(meta);
    } catch {
        return '—';
    }
};

const filteredLogs = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.logs;
    }

    return props.logs.filter((log) => {
        const actionMatch = log.action && log.action.toLowerCase().includes(q);
        const userMatch = log.user?.email && log.user.email.toLowerCase().includes(q);
        const ipMatch = log.ip && log.ip.toLowerCase().includes(q);
        const metaMatch = formatMeta(log.meta).toLowerCase().includes(q);

        return actionMatch || userMatch || ipMatch || metaMatch;
    });
});
</script>

<template>
    <AppLayout :title="t('audit.title')" :description="t('audit.description')" :full-width="true">
        <Head title="Audit" />

        <!-- Metric Stat Cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Audit Logs</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ logs.length }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs transition hover:border-neutral-300">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">Scope</p>
                    <p class="mt-1 text-sm font-semibold text-neutral-700">Latest 100 System Events</p>
                </div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="space-y-4">
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
                        placeholder="Search logs by action, user, or IP..."
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
                    {{ filteredLogs.length }} {{ filteredLogs.length === 1 ? 'event' : 'events' }}
                </p>
            </div>

            <!-- Table Container -->
            <div class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs">
                <table class="min-w-full divide-y divide-neutral-100 text-sm">
                    <thead class="bg-neutral-50/70 text-left text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('common.when') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.user') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.action') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.details') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="log in filteredLogs" :key="log.id" class="hover:bg-neutral-50/60 transition">
                            <td class="whitespace-nowrap px-5 py-3.5 text-xs text-neutral-500">{{ log.created_at || '—' }}</td>
                            <td class="px-5 py-3.5 text-xs font-medium text-neutral-900">
                                <span v-if="log.user">{{ log.user.email }}</span>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="rounded-md bg-neutral-100 px-2 py-0.5 font-mono text-[11px] text-neutral-800 font-medium">
                                    {{ log.action }}
                                </span>
                            </td>
                            <td class="max-w-md truncate px-5 py-3.5 font-mono text-xs text-neutral-500" :title="formatMeta(log.meta)">
                                {{ formatMeta(log.meta) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-neutral-500">{{ log.ip || '—' }}</td>
                        </tr>
                        <tr v-if="!filteredLogs.length">
                            <td colspan="5" class="px-5 py-12 text-center text-xs text-neutral-500">
                                {{ search ? 'No audit logs match your search query.' : t('audit.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
