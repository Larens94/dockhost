<!-- Index.vue — Index component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { Link } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

defineProps({
    domains: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AppLayout
        :title="t('toolkit_index.title')"
        :description="t('toolkit_index.description')"
    >
        <div
            v-if="domains.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">{{ t('toolkit_index.empty_title') }}</p>
            <p class="mt-1 text-sm text-zinc-500">
                {{ t('toolkit_index.empty_body') }}
            </p>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('common.domain') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.space') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.application') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.source') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="domain in domains" :key="domain.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link
                                :href="`/domains/${domain.id}?tab=laravel`"
                                class="font-medium text-zinc-900 hover:underline"
                            >
                                {{ domain.fqdn }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                v-if="domain.subscription_id"
                                :href="`/subscriptions/${domain.subscription_id}`"
                                class="text-zinc-700 hover:underline"
                            >
                                {{ domain.subscription?.name || t('common.space') }}
                            </Link>
                            <span v-else class="text-zinc-500">—</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ domain.customer?.name }}</td>
                        <td class="min-w-0 px-4 py-3 text-zinc-500">
                            <a
                                v-if="domain.dokploy_application_url"
                                :href="domain.dokploy_application_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex max-w-full whitespace-normal font-medium text-zinc-700 underline underline-offset-2 break-all [overflow-wrap:anywhere] hover:text-zinc-900"
                            >
                                {{ domain.dokploy_application?.dokploy_application_id }}
                            </a>
                            <span v-else class="break-all">{{
                                domain.dokploy_application?.dokploy_application_id
                            }}</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">Dokploy</td>
                    </tr>
                </tbody>
            </template>

            <template #cards>
                <article
                    v-for="domain in domains"
                    :key="domain.id"
                    class="min-w-0 rounded-xl border border-neutral-200 bg-white p-4 shadow-sm"
                >
                    <Link
                        :href="`/domains/${domain.id}?tab=laravel`"
                        class="font-semibold break-all hover:underline"
                    >
                        {{ domain.fqdn }}
                    </Link>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.space') }}</dt>
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
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.customer') }}</dt>
                            <dd class="font-medium text-right">{{ domain.customer?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ t('common.application') }}</dt>
                            <dd class="mt-1 font-medium break-all">
                                <a
                                    v-if="domain.dokploy_application_url"
                                    :href="domain.dokploy_application_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="underline underline-offset-2 hover:text-zinc-900"
                                >
                                    {{ domain.dokploy_application?.dokploy_application_id }}
                                </a>
                                <span v-else>{{ domain.dokploy_application?.dokploy_application_id }}</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('common.source') }}</dt>
                            <dd class="max-w-[60%] text-right font-medium">Dokploy</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
