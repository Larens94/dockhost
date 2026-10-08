<!-- Create.vue — Create component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    customers: {
        type: Array,
        required: true,
    },
    plans: {
        type: Array,
        required: true,
    },
    selected_customer_id: {
        type: Number,
        default: null,
    },
});

const form = useForm({
    customer_id: props.selected_customer_id || props.customers[0]?.id || '',
    service_plan_id: props.plans[0]?.id || '',
    name: '',
});

const submit = () => {
    form.post('/subscriptions');
};
</script>

<template>
    <AppLayout title="Nuovo spazio" description="Associa un cliente a un piano di servizio.">
        <template #actions>
            <Link href="/subscriptions" class="text-sm text-zinc-600 hover:text-zinc-900">Indietro</Link>
        </template>

        <form
            class="max-w-xl space-y-5 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <label class="block text-sm font-medium" for="customer_id">Cliente</label>
                <select
                    id="customer_id"
                    v-model="form.customer_id"
                    required
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                >
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.name }}
                    </option>
                </select>
                <p v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">{{ form.errors.customer_id }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="service_plan_id">Piano</label>
                <select
                    id="service_plan_id"
                    v-model="form.service_plan_id"
                    required
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                >
                    <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                        {{ plan.name }}
                    </option>
                </select>
                <p v-if="form.errors.service_plan_id" class="mt-1 text-sm text-red-600">{{ form.errors.service_plan_id }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="name">Nome spazio</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    placeholder="acme-web"
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <button
                type="submit"
                class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                Crea
            </button>
        </form>
    </AppLayout>
</template>
