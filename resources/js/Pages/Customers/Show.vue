<!-- Show.vue — Show component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Customer show copy uses panel translations.
-->

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
    plans: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    customer_id: props.customer.id,
    name: '',
    service_plan_id: props.plans[0]?.id || '',
});

const customerForm = useForm({
    name: props.customer.name,
    email: props.customer.email || '',
    notes: props.customer.notes || '',
});

const deleteCustomerForm = useForm({});

const submit = () => {
    form.post('/subscriptions');
};

const saveCustomer = () => {
    customerForm.put(`/customers/${props.customer.id}`);
};

const deleteCustomer = () => {
    if (!window.confirm(t('customers_form.delete_confirm', { name: props.customer.name }))) {
        return;
    }

    deleteCustomerForm.delete(`/customers/${props.customer.id}`);
};

const inputClass =
    'mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10';
</script>

<template>
    <AppLayout :title="customer.name" :description="customer.email || t('customers_form.show_fallback')">
        <template #actions>
            <Link href="/customers" class="text-sm text-zinc-600 hover:text-zinc-900">{{ t('common.back') }}</Link>
        </template>

        <section class="mb-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">{{ t('customers_form.profile') }}</h2>
            <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveCustomer">
                <div>
                    <label class="block text-sm font-medium" for="customer-name">{{ t('common.name') }}</label>
                    <input id="customer-name" v-model="customerForm.name" type="text" required :class="inputClass" />
                    <p v-if="customerForm.errors.name" class="mt-1 text-sm text-red-600">{{ customerForm.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="customer-email">{{ t('common.email') }}</label>
                    <input id="customer-email" v-model="customerForm.email" type="email" :class="inputClass" />
                    <p v-if="customerForm.errors.email" class="mt-1 text-sm text-red-600">{{ customerForm.errors.email }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium" for="customer-notes">{{ t('common.notes') }}</label>
                    <textarea id="customer-notes" v-model="customerForm.notes" rows="3" :class="inputClass" />
                    <p v-if="customerForm.errors.notes" class="mt-1 text-sm text-red-600">{{ customerForm.errors.notes }}</p>
                </div>
                <div class="sm:col-span-2 flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="customerForm.processing"
                    >
                        {{ t('customers_form.save') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-sm font-medium text-red-800 hover:bg-red-100 disabled:opacity-50"
                        :disabled="deleteCustomerForm.processing || customer.subscriptions.length > 0"
                        @click="deleteCustomer"
                    >
                        {{ t('customers_form.delete') }}
                    </button>
                </div>
                <p v-if="customer.subscriptions.length > 0" class="sm:col-span-2 text-sm text-zinc-500">
                    {{ t('customers_form.delete_blocked') }}
                </p>
                <p v-if="deleteCustomerForm.errors.customer" class="sm:col-span-2 text-sm text-red-600">
                    {{ deleteCustomerForm.errors.customer }}
                </p>
            </form>
        </section>

        <section class="mb-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">{{ t('customers_form.new_space') }}</h2>
            <p class="mt-1 mb-5 text-sm text-zinc-500">
                {{ t('customers_form.new_space_help') }}
            </p>
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium" for="name">{{ t('customers_form.space_name') }}</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="vibesbridge.space"
                        :class="inputClass"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="service_plan_id">{{ t('common.plan') }}</label>
                    <select id="service_plan_id" v-model="form.service_plan_id" required :class="inputClass">
                        <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                            {{ plan.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.service_plan_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.service_plan_id }}
                    </p>
                    <p v-if="plans.length === 0" class="mt-1 text-sm text-amber-700">
                        {{ t('customers_form.plan_required') }}
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="form.processing || plans.length === 0"
                    >
                        {{ t('customers_form.create_space') }}
                    </button>
                </div>
            </form>
        </section>

        <div
            v-if="customer.subscriptions.length === 0"
            class="rounded-xl border border-dashed border-zinc-300 bg-white px-6 py-12 text-center text-sm text-zinc-500"
        >
            {{ t('customers_form.empty_spaces') }}
        </div>

        <div v-else class="grid gap-4">
            <article
                v-for="subscription in customer.subscriptions"
                :key="subscription.id"
                class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Link
                            :href="`/subscriptions/${subscription.id}`"
                            class="font-semibold hover:underline"
                        >
                            {{ subscription.name }}
                        </Link>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ subscription.service_plan?.name || t('common.plan') }} ·
                            {{ t('customers_form.domains_count', { count: subscription.domains_count || 0 }) }}
                        </p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-800">
                        {{ subscription.status }}
                    </span>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
