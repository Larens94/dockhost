<!-- Show.vue — Space detail and management component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Delete opens DeleteSpaceModal; the typed confirmation must match deletion_confirmation.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Danger zone deletes the space and its Dokploy apps.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Space show copy uses panel translations.
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
  agent:   composer-2.5 | cursor | 2026-10-16 | s_rename_space | Pencil + RenameSpaceModal on show page
-->

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';
import DeleteSpaceModal from '../../Components/DeleteSpaceModal.vue';
import RenameSpaceModal from '../../Components/RenameSpaceModal.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const { t } = usePanelTranslations();

defineProps({
    subscription: {
        type: Object,
        required: true,
    },
});

const showDeleteModal = ref(false);
const showRenameModal = ref(false);

const databaseLabel = (domain) => {
    const name = domain.database_accounts?.[0]?.database_name;

    return name || t('common.no');
};

const sftpLabel = (domain) => domain.sftp_users?.[0]?.username || '—';

const stackLabel = (domain) => domain.stack_label || domain.stack || t('common.hosting_only');
</script>

<template>
    <AppLayout
        :title="subscription.name"
        :description="t('spaces_form.show_description', { customer: subscription.customer?.name || t('common.fallback_customer'), plan: subscription.service_plan?.name || t('common.fallback_plan') })"
        :full-width="true"
    >
        <template #title>
            <span class="inline-flex min-w-0 items-center gap-2">
                <span class="truncate">{{ subscription.name }}</span>
                <button
                    type="button"
                    class="inline-flex shrink-0 rounded-lg p-1.5 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700"
                    :title="t('spaces_form.edit_name')"
                    :aria-label="t('spaces_form.edit_name')"
                    @click="showRenameModal = true"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                        />
                    </svg>
                </button>
            </span>
        </template>

        <template #actions>
            <div class="flex items-center gap-2">
                <Link
                    v-if="subscription.customer_id"
                    :href="`/customers/${subscription.customer_id}`"
                    class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 hover:text-neutral-900"
                >
                    <svg class="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span>{{ t('common.customer') }}</span>
                </Link>
                <Link
                    :href="`/subscriptions/${subscription.id}/domains/create`"
                    class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>{{ t('spaces_form.add_domain') }}</span>
                </Link>
            </div>
        </template>

        <div class="space-y-8">
            <!-- Empty Domains State -->
            <div
                v-if="subscription.domains.length === 0"
                class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-16 text-center shadow-xs"
            >
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                    </svg>
                </div>
                <p class="mt-4 text-sm font-semibold text-neutral-900">{{ t('spaces_form.empty_title') }}</p>
                <p class="mt-1 text-xs text-neutral-500 max-w-md mx-auto">{{ t('spaces_form.empty_body') }}</p>
                <Link
                    :href="`/subscriptions/${subscription.id}/domains/create`"
                    class="mt-5 inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-neutral-800 transition"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>{{ t('spaces_form.add_domain') }}</span>
                </Link>
            </div>

            <!-- Domains Table Container -->
            <div v-else class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-700">
                        {{ t('common.domains') }} ({{ subscription.domains.length }})
                    </h2>
                </div>

                <ResponsiveTable>
                    <template #table>
                        <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                            <tr>
                                <th class="px-5 py-3.5">{{ t('common.fqdn') }}</th>
                                <th class="px-5 py-3.5">{{ t('common.infra') }}</th>
                                <th class="px-5 py-3.5">{{ t('common.stack') }}</th>
                                <th class="px-5 py-3.5">{{ t('common.database') }}</th>
                                <th class="px-5 py-3.5">{{ t('common.sftp') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="domain in subscription.domains" :key="domain.id" class="group hover:bg-neutral-50/60 transition">
                                <td class="px-5 py-3.5">
                                    <Link
                                        :href="`/domains/${domain.id}`"
                                        class="font-semibold text-neutral-900 group-hover:underline flex items-center gap-2"
                                    >
                                        <svg class="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                                        </svg>
                                        <span>{{ domain.fqdn }}</span>
                                    </Link>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-neutral-500">
                                    <span class="rounded-md bg-neutral-100 px-2 py-0.5 font-mono text-[11px] text-neutral-700">
                                        {{ domain.infra_slug }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-xs">
                                    <a
                                        v-if="domain.dokploy_application_url"
                                        :href="domain.dokploy_application_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="font-medium text-neutral-900 underline underline-offset-2 hover:text-black"
                                    >
                                        {{ stackLabel(domain) }}
                                    </a>
                                    <span v-else class="text-neutral-700">{{ stackLabel(domain) }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-neutral-500">{{ databaseLabel(domain) }}</td>
                                <td class="px-5 py-3.5 text-xs text-neutral-500">{{ sftpLabel(domain) }}</td>
                            </tr>
                        </tbody>
                    </template>

                    <template #cards>
                        <article
                            v-for="domain in subscription.domains"
                            :key="domain.id"
                            class="rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs"
                        >
                            <Link :href="`/domains/${domain.id}`" class="font-semibold text-neutral-900 hover:underline">
                                {{ domain.fqdn }}
                            </Link>
                            <dl class="mt-3 space-y-2 text-xs">
                                <div class="flex justify-between gap-3 text-neutral-600">
                                    <dt class="text-neutral-400">{{ t('common.infra') }}</dt>
                                    <dd class="font-medium text-right">{{ domain.infra_slug }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 text-neutral-600">
                                    <dt class="text-neutral-400">{{ t('common.stack') }}</dt>
                                    <dd class="font-medium text-right">
                                        <a
                                            v-if="domain.dokploy_application_url"
                                            :href="domain.dokploy_application_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="underline underline-offset-2"
                                        >
                                            {{ stackLabel(domain) }}
                                        </a>
                                        <span v-else>{{ stackLabel(domain) }}</span>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3 text-neutral-600">
                                    <dt class="text-neutral-400">{{ t('common.database') }}</dt>
                                    <dd class="font-medium text-right">{{ databaseLabel(domain) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 text-neutral-600">
                                    <dt class="text-neutral-400">{{ t('common.sftp') }}</dt>
                                    <dd class="font-medium text-right">{{ sftpLabel(domain) }}</dd>
                                </div>
                            </dl>
                        </article>
                    </template>
                </ResponsiveTable>
            </div>

            <!-- Danger Zone Card -->
            <section class="overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-xs">
                <div class="border-b border-rose-100 bg-rose-50/40 px-6 py-4 sm:px-8">
                    <h2 class="text-sm font-bold text-rose-800">{{ t('common.danger_zone') }}</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">
                        {{ t('spaces_form.danger_body') }}
                    </p>
                </div>
                <div class="p-6 sm:p-8">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                        @click="showDeleteModal = true"
                    >
                        {{ t('spaces.modal.submit') }}
                    </button>
                </div>
            </section>
        </div>

        <RenameSpaceModal
            v-if="showRenameModal"
            :space="subscription"
            @close="showRenameModal = false"
        />

        <DeleteSpaceModal
            v-if="showDeleteModal"
            :space="subscription"
            @close="showDeleteModal = false"
        />
    </AppLayout>
</template>
