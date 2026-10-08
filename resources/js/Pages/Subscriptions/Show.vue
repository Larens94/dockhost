<!-- Show.vue — Show component.

  exports: defineProps
  used_by: none
  rules:   Delete opens DeleteSpaceModal; the typed confirmation must match deletion_confirmation.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Danger zone deletes the space and its Dokploy apps.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Space show copy uses panel translations.
-->

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();
import DeleteSpaceModal from '../../Components/DeleteSpaceModal.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

defineProps({
    subscription: {
        type: Object,
        required: true,
    },
});

const showDeleteModal = ref(false);

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
    >
        <template #actions>
            <Link
                :href="`/customers/${subscription.customer_id}`"
                class="text-sm text-zinc-600 hover:text-zinc-900"
            >
                {{ t('common.customer') }}
            </Link>
            <Link
                :href="`/subscriptions/${subscription.id}/domains/create`"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                {{ t('spaces_form.add_domain') }}
            </Link>
        </template>

        <div
            v-if="subscription.domains.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">{{ t('spaces_form.empty_title') }}</p>
            <p class="mt-1 text-sm text-zinc-500">
                {{ t('spaces_form.empty_body') }}
            </p>
            <Link
                :href="`/subscriptions/${subscription.id}/domains/create`"
                class="mt-4 inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                {{ t('spaces_form.add_domain') }}
            </Link>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('common.fqdn') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.infra') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.stack') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.database') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.sftp') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="domain in subscription.domains" :key="domain.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link
                                :href="`/domains/${domain.id}`"
                                class="font-medium text-zinc-900 hover:underline"
                            >
                                {{ domain.fqdn }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ domain.infra_slug }}</td>
                        <td class="px-4 py-3 text-zinc-500">
                            <a
                                v-if="domain.dokploy_application_url"
                                :href="domain.dokploy_application_url"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-zinc-900 underline underline-offset-2"
                            >
                                {{ stackLabel(domain) }}
                            </a>
                            <span v-else>{{ stackLabel(domain) }}</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ databaseLabel(domain) }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ sftpLabel(domain) }}</td>
                    </tr>
                </tbody>
            </template>

            <template #cards>
                <article
                    v-for="domain in subscription.domains"
                    :key="domain.id"
                    class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm"
                >
                    <Link :href="`/domains/${domain.id}`" class="font-semibold hover:underline">
                        {{ domain.fqdn }}
                    </Link>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.infra') }}</dt>
                            <dd class="font-medium text-right">{{ domain.infra_slug }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.stack') }}</dt>
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
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.database') }}</dt>
                            <dd class="font-medium text-right">{{ databaseLabel(domain) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.sftp') }}</dt>
                            <dd class="font-medium text-right">{{ sftpLabel(domain) }}</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </ResponsiveTable>

        <section class="mt-8 rounded-xl border border-red-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-red-800">{{ t('common.danger_zone') }}</h2>
            <p class="mt-1 mb-5 text-sm text-zinc-500">
                {{ t('spaces_form.danger_body') }}
            </p>
            <button
                type="button"
                class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-sm font-medium text-red-800 hover:bg-red-100"
                @click="showDeleteModal = true"
            >
                {{ t('spaces.modal.submit') }}
            </button>
        </section>

        <DeleteSpaceModal
            v-if="showDeleteModal"
            :space="subscription"
            @close="showDeleteModal = false"
        />
    </AppLayout>
</template>
