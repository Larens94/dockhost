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
    plans: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AppLayout :title="t('plans.title')" :description="t('plans.description')">
        <template #actions>
            <Link
                href="/service-plans/create"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                {{ t('plans.new') }}
            </Link>
        </template>

        <div
            v-if="plans.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">{{ t('plans.empty_title') }}</p>
            <p class="mt-1 text-sm text-zinc-500">{{ t('plans.empty_body') }}</p>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('common.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.slug') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.domains') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('layout.nav.spaces') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="plan in plans" :key="plan.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link :href="`/service-plans/${plan.id}`" class="font-medium text-zinc-900 hover:underline">
                                {{ plan.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ plan.slug }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ plan.max_domains ?? t('common.unlimited') }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ plan.subscriptions_count }}</td>
                    </tr>
                </tbody>
            </template>

            <template #cards>
                <article
                    v-for="plan in plans"
                    :key="plan.id"
                    class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm"
                >
                    <Link :href="`/service-plans/${plan.id}`" class="font-semibold hover:underline">
                        {{ plan.name }}
                    </Link>
                    <p class="mt-1 text-sm text-zinc-500">{{ plan.slug }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-zinc-500">{{ t('common.domains') }}</dt>
                            <dd class="font-medium">{{ plan.max_domains ?? t('common.unlimited') }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">{{ t('layout.nav.spaces') }}</dt>
                            <dd class="font-medium">{{ plan.subscriptions_count }}</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
