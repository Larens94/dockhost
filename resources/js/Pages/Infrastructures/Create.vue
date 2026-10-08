<!-- Create.vue — Create infrastructure form (Inertia).

  exports: defineProps | form | pickerServices
  used_by: app/Http/Controllers/InfrastructureController.php → create/store
  rules:   Creating an infra provisions ONE Dokploy compose; services share dokploy-network (server-side).
           Do NOT toggle Dokploy Isolated Deployments ON. Do NOT mention {slug}-db/{slug}-storage as the create model.
           Never show API keys or generated DB/SFTP passwords beyond one-time flash from backend.
  agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Shared-network create guidance
-->

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ServicePicker from '../../Components/ServicePicker.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    suggested_slug: {
        type: String,
        default: 'infra1',
    },
    catalog: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    slug: props.suggested_slug,
    name: '',
    template: 'base',
    enabled_services: props.catalog
        .filter((service) => service.required || service.default_enabled)
        .map((service) => service.key),
});

const pickerServices = computed(() =>
    props.catalog.map((service) => ({
        ...service,
        hostname: service.hostname_suffix ? `${form.slug || 'slug'}-${service.hostname_suffix}` : null,
    })),
);

const toggleOptional = (key) => {
    if (form.enabled_services.includes(key)) {
        form.enabled_services = form.enabled_services.filter((item) => item !== key);
        return;
    }

    form.enabled_services = [...form.enabled_services, key];
};

const submit = () => {
    form.post('/infrastructures');
};

const inputClass =
    'mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10';
</script>

<template>
    <AppLayout
        title="Nuova infrastruttura"
        description="Crea un progetto Dokploy nuovo già popolato con lo stack base."
    >
        <template #actions>
            <Link href="/infrastructures" class="text-sm text-zinc-600 hover:text-zinc-900">Indietro</Link>
        </template>

        <form
            class="max-w-2xl space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <p class="text-sm break-words text-zinc-500 [overflow-wrap:anywhere]">
                I servizi con dominio (phpMyAdmin, pgAdmin, console MinIO) usano il wildcard
                <span class="font-medium text-zinc-700">*.cloud.silicoreautomation.com</span>.
                Hostname interni:
                <span class="font-medium text-zinc-700">{{ form.slug || 'slug' }}-mariadb</span>.
            </p>

            <div>
                <label class="block text-sm font-medium" for="slug">Slug</label>
                <input id="slug" v-model="form.slug" type="text" required :class="inputClass" />
                <p v-if="form.errors.slug" class="mt-1 min-w-0 text-sm break-words text-red-600 [overflow-wrap:anywhere]">
                    {{ form.errors.slug }}
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="name">Nome</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    :placeholder="form.slug || 'infra1'"
                    :class="inputClass"
                />
                <p class="mt-1 text-xs text-zinc-500">Opzionale. Se vuoto si usa lo slug.</p>
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium" for="template">Template</label>
                <select id="template" v-model="form.template" :class="inputClass">
                    <option value="base">base (MariaDB, Postgres, SFTP)</option>
                </select>
            </div>

            <div>
                <p class="text-sm font-medium">Servizi</p>
                <p class="mt-1 mb-3 text-xs text-zinc-500">
                    Clicca una card per attivarla. La base resta bloccata. Redis, pgAdmin e MinIO si possono aggiungere
                    anche dopo.
                </p>
                <ServicePicker
                    :services="pickerServices"
                    :selected="form.enabled_services"
                    :disabled="form.processing"
                    @toggle="toggleOptional"
                />
            </div>

            <button
                type="submit"
                class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                Crea infrastruttura
            </button>
        </form>
    </AppLayout>
</template>
