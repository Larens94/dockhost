<!-- Show.vue — Plan detail and subscribed spaces component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Catalog of plans to attach to spaces.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
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
    <AppLayout :title="plan.name" :description="t('plans.show_description', { slug: plan.slug })" :full-width="true">
        <template #actions>
            <Link
                href="/service-plans"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 hover:text-neutral-900"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>{{ t('common.all_plans') }}</span>
            </Link>
        </template>

        <!-- Metric Stat Cards -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('common.domains') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ plan.max_domains ?? '∞' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('common.disk') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ plan.disk_mb ? `${plan.disk_mb} MB` : '∞' }}</p>
                </div>
            </div>

            <div class="flex items-center gap-4 rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-800">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ t('layout.nav.spaces') }}</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-neutral-900">{{ plan.subscriptions.length }}</p>
                </div>
            </div>
        </div>

        <!-- Subscribed Spaces Table -->
        <div class="space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-700">
                {{ t('layout.nav.spaces') }} ({{ plan.subscriptions.length }})
            </h2>

            <div
                v-if="plan.subscriptions.length === 0"
                class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-12 text-center text-xs text-neutral-500 shadow-xs"
            >
                {{ t('plans.empty_spaces') }}
            </div>

            <div v-else class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-neutral-200/80 bg-neutral-50/70 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                        <tr>
                            <th class="px-5 py-3.5">{{ t('plans.space') }}</th>
                            <th class="px-5 py-3.5">{{ t('common.customer') }}</th>
                            <th class="px-5 py-3.5 text-center">{{ t('common.domains') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="space in plan.subscriptions" :key="space.id" class="group hover:bg-neutral-50/60 transition">
                            <td class="px-5 py-3.5">
                                <Link :href="`/subscriptions/${space.id}`" class="font-semibold text-neutral-900 group-hover:underline">
                                    {{ space.name }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5 text-xs text-neutral-600">
                                <Link v-if="space.customer" :href="`/customers/${space.customer.id}`" class="hover:underline font-medium text-neutral-800">
                                    {{ space.customer.name }}
                                </Link>
                                <span v-else class="text-neutral-300">—</span>
                            </td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                    {{ space.domains_count }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
