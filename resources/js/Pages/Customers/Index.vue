<!-- Index.vue — Index component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ResponsiveTable from '../../Components/ResponsiveTable.vue';

const props = defineProps({
    customers: {
        type: Array,
        required: true,
    },
});

const domainCount = computed(() =>
    props.customers.reduce((total, customer) => total + (customer.domains_count || 0), 0),
);
const spazioCount = computed(() =>
    props.customers.reduce((total, customer) => total + (customer.subscriptions_count || 0), 0),
);
</script>

<template>
    <AppLayout title="Clienti" description="Account che possiedono spazi, domini, database e SFTP.">
        <template #actions>
            <Link
                href="/customers/create"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                Nuovo cliente
            </Link>
        </template>

        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Clienti</p>
                <p class="mt-2 text-2xl font-semibold">{{ customers.length }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Spazi</p>
                <p class="mt-2 text-2xl font-semibold">{{ spazioCount }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Domini</p>
                <p class="mt-2 text-2xl font-semibold">{{ domainCount }}</p>
            </div>
        </div>

        <div
            v-if="customers.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">Nessun cliente</p>
            <p class="mt-1 text-sm text-zinc-500">Crea un cliente, poi uno spazio e infine un dominio.</p>
            <Link
                href="/customers/create"
                class="mt-4 inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white"
            >
                Nuovo cliente
            </Link>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nome</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Spazi</th>
                        <th class="px-4 py-3 font-medium">Domini</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <tr v-for="customer in customers" :key="customer.id" class="hover:bg-zinc-50">
                        <td class="px-4 py-3">
                            <Link :href="`/customers/${customer.id}`" class="font-medium text-zinc-900 hover:underline">
                                {{ customer.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-zinc-500">{{ customer.email || '—' }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ customer.subscriptions_count }}</td>
                        <td class="px-4 py-3 text-zinc-500">{{ customer.domains_count }}</td>
                    </tr>
                </tbody>
            </template>

            <template #cards>
                <article
                    v-for="customer in customers"
                    :key="customer.id"
                    class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm"
                >
                    <Link :href="`/customers/${customer.id}`" class="font-semibold hover:underline">
                        {{ customer.name }}
                    </Link>
                    <p v-if="customer.email" class="mt-1 text-sm text-zinc-500">{{ customer.email }}</p>
                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <dt class="text-zinc-500">Spazi</dt>
                            <dd class="font-medium">{{ customer.subscriptions_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Domini</dt>
                            <dd class="font-medium">{{ customer.domains_count }}</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
