<!-- Panel/Audit/Index.vue — Admin audit log (latest 100).

  exports: none
  used_by: AuditLogController
  rules:   Admin-only route — no secrets in meta display.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_audit | Audit list UI.
-->

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    logs: {
        type: Array,
        default: () => [],
    },
});

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
</script>

<template>
    <AppLayout title="Audit" description="Ultime azioni rilevanti nel pannello.">
        <Head title="Audit" />

        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Quando</th>
                        <th class="px-4 py-3">Utente</th>
                        <th class="px-4 py-3">Azione</th>
                        <th class="px-4 py-3">Dettagli</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    <tr v-for="log in logs" :key="log.id">
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-600">{{ log.created_at || '—' }}</td>
                        <td class="px-4 py-3">
                            <span v-if="log.user">{{ log.user.email }}</span>
                            <span v-else class="text-zinc-400">—</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ log.action }}</td>
                        <td class="max-w-md truncate px-4 py-3 text-xs text-zinc-600" :title="formatMeta(log.meta)">
                            {{ formatMeta(log.meta) }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ log.ip || '—' }}</td>
                    </tr>
                    <tr v-if="!logs.length">
                        <td colspan="5" class="px-4 py-8 text-center text-zinc-500">Nessuna voce di audit.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
