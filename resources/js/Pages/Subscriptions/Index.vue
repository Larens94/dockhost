<!-- Index.vue — Index component.

  exports: defineProps
  used_by: none
  rules:   Delete opens DeleteSpaceModal; the typed confirmation must match deletion_confirmation.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Delete action on the spaces list.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Index chrome uses panel translations.
-->

<script setup>
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DeleteSpaceModal from '../../Components/DeleteSpaceModal.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const { t } = usePanelTranslations();

defineProps({
    subscriptions: {
        type: Array,
        required: true,
    },
});

const spaceToDelete = ref(null);
</script>

<template>
    <AppLayout :title="t('spaces.title')" :description="t('spaces.description')">
        <template #actions>
            <Link
                href="/subscriptions/create"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                {{ t('spaces.new') }}
            </Link>
        </template>

        <div
            v-if="subscriptions.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">{{ t('spaces.empty_title') }}</p>
            <p class="mt-1 text-sm text-zinc-500">{{ t('spaces.empty_body') }}</p>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ t('spaces.name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('spaces.customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('spaces.plan') }}</th>
                        <th class="px-4 py-3 font-medium">{{ t('spaces.domains') }}</th>
                        <th class="px-4 py-3 text-right font-medium">{{ t('spaces.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="space in subscriptions" :key="space.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link :href="`/subscriptions/${space.id}`" class="font-medium text-zinc-900 hover:underline">
                                {{ space.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">
                            <Link v-if="space.customer" :href="`/customers/${space.customer.id}`" class="hover:underline">
                                {{ space.customer.name }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ space.service_plan?.name }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ space.domains_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-800 hover:bg-red-100"
                                @click="spaceToDelete = space"
                            >
                                {{ t('spaces.delete') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </template>

            <template #cards>
                <article
                    v-for="space in subscriptions"
                    :key="space.id"
                    class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm"
                >
                    <Link :href="`/subscriptions/${space.id}`" class="font-semibold hover:underline">
                        {{ space.name }}
                    </Link>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('spaces.customer') }}</dt>
                            <dd class="font-medium text-right">
                                <Link
                                    v-if="space.customer"
                                    :href="`/customers/${space.customer.id}`"
                                    class="hover:underline"
                                >
                                    {{ space.customer.name }}
                                </Link>
                                <span v-else>—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('spaces.plan') }}</dt>
                            <dd class="font-medium text-right">{{ space.service_plan?.name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">{{ t('spaces.domains') }}</dt>
                            <dd class="font-medium text-right">{{ space.domains_count }}</dd>
                        </div>
                    </dl>
                    <button
                        type="button"
                        class="mt-4 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-800 hover:bg-red-100"
                        @click="spaceToDelete = space"
                    >
                        {{ t('spaces.delete') }}
                    </button>
                </article>
            </template>
        </ResponsiveTable>

        <DeleteSpaceModal v-if="spaceToDelete" :space="spaceToDelete" @close="spaceToDelete = null" />
    </AppLayout>
</template>
