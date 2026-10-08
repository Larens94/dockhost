<!-- Index.vue — Index component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

defineProps({
    subscriptions: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AppLayout title="Spazi" description="Iscrizioni: un cliente, un piano, i domini del sito.">
        <template #actions>
            <Link
                href="/subscriptions/create"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                Nuovo spazio
            </Link>
        </template>

        <div
            v-if="subscriptions.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">Nessuno spazio</p>
            <p class="mt-1 text-sm text-zinc-500">Crea un cliente e un piano, poi associa uno spazio.</p>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nome</th>
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium">Piano</th>
                        <th class="px-4 py-3 font-medium">Domini</th>
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
                            <dt class="text-zinc-500">Cliente</dt>
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
                            <dt class="text-zinc-500">Piano</dt>
                            <dd class="font-medium text-right">{{ space.service_plan?.name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">Domini</dt>
                            <dd class="font-medium text-right">{{ space.domains_count }}</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
