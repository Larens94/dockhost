<!-- Index.vue — Elenco hosting visibili all'utente (tutti per admin, pivot per membri).

  exports: defineProps
  used_by: DomainController::index
  rules:   Labels come from shared panel translations. Italian is the default locale.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | Member landing page.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Index chrome uses panel translations.
-->

<script setup>
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const { t } = usePanelTranslations();

defineProps({
    domains: {
        type: Array,
        required: true,
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <AppLayout
        :title="isAdmin ? t('domains.title') : t('domains.my_hosting_title')"
        :description="isAdmin ? t('domains.description_admin') : t('domains.description_member')"
    >
        <div
            v-if="domains.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">{{ t('domains.empty_title') }}</p>
            <p v-if="!isAdmin" class="mt-1 text-sm text-zinc-500">
                {{ t('domains.empty_member') }}
            </p>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('domains.domain') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('domains.customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('domains.stack') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="domain in domains" :key="domain.id" class="hover:bg-zinc-50/80">
                        <td class="px-4 py-3">
                            <Link :href="`/domains/${domain.id}`" class="font-medium text-zinc-900 hover:underline">
                                {{ domain.fqdn }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600">
                            {{ domain.customer?.name || '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-zinc-600">
                            {{ domain.stack_label || domain.stack || '—' }}
                        </td>
                    </tr>
                </tbody>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
