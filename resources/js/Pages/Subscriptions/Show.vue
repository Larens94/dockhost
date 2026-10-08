<!-- Show.vue — Show component.

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
    subscription: {
        type: Object,
        required: true,
    },
});

const databaseLabel = (domain) => {
    const name = domain.database_accounts?.[0]?.database_name;

    return name || 'No';
};

const sftpLabel = (domain) => domain.sftp_users?.[0]?.username || '—';

const stackLabel = (domain) => domain.stack_label || domain.stack || 'Solo hosting';
</script>

<template>
    <AppLayout
        :title="subscription.name"
        :description="`Spazio di ${subscription.customer?.name || 'cliente'} · ${subscription.service_plan?.name || 'piano'}`"
    >
        <template #actions>
            <Link
                :href="`/customers/${subscription.customer_id}`"
                class="text-sm text-zinc-600 hover:text-zinc-900"
            >
                Cliente
            </Link>
            <Link
                :href="`/subscriptions/${subscription.id}/domains/create`"
                class="inline-flex items-center rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                Aggiungi dominio
            </Link>
        </template>

        <div
            v-if="subscription.domains.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center"
        >
            <p class="text-sm font-medium">Nessun dominio su questo spazio</p>
            <p class="mt-1 text-sm text-zinc-500">
                Aggiungi un FQDN su un’infra DokHosts. Database, SFTP e stack applicativo (HTML, PHP, Laravel, …) si
                scelgono alla creazione o dopo, dalla scheda del dominio.
            </p>
            <Link
                :href="`/subscriptions/${subscription.id}/domains/create`"
                class="mt-4 inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                Aggiungi dominio
            </Link>
        </div>

        <ResponsiveTable v-else>
            <template #table>
                <thead class="border-b border-neutral-200 bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">FQDN</th>
                        <th class="px-4 py-3 font-medium">Infra</th>
                        <th class="px-4 py-3 font-medium">Stack</th>
                        <th class="px-4 py-3 font-medium">Database</th>
                        <th class="px-4 py-3 font-medium">SFTP</th>
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
                            <dt class="text-zinc-500">Infra</dt>
                            <dd class="font-medium text-right">{{ domain.infra_slug }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">Stack</dt>
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
                            <dt class="text-zinc-500">Database</dt>
                            <dd class="font-medium text-right">{{ databaseLabel(domain) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-zinc-500">SFTP</dt>
                            <dd class="font-medium text-right">{{ sftpLabel(domain) }}</dd>
                        </div>
                    </dl>
                </article>
            </template>
        </ResponsiveTable>
    </AppLayout>
</template>
