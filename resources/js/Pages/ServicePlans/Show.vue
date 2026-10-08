<!-- Show.vue — Show component.

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

defineProps({
    plan: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AppLayout :title="plan.name" :description="t('plans.show_description', { slug: plan.slug })">
        <template #actions>
            <Link href="/service-plans" class="text-sm text-zinc-600 hover:text-zinc-900">{{ t('common.all_plans') }}</Link>
        </template>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.domains') }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ plan.max_domains ?? '∞' }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('common.disk') }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ plan.disk_mb ? `${plan.disk_mb} MB` : '∞' }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('layout.nav.spaces') }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ plan.subscriptions.length }}</p>
            </div>
        </div>

        <div v-if="plan.subscriptions.length === 0" class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center text-sm text-zinc-500">
            {{ t('plans.empty_spaces') }}
        </div>

        <div v-else class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('plans.space') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('common.domains') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="space in plan.subscriptions" :key="space.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link :href="`/subscriptions/${space.id}`" class="font-medium hover:underline">
                                {{ space.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ space.customer?.name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ space.domains_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
