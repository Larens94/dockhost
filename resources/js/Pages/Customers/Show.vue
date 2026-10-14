<!-- Show.vue — Customer detail and management component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Customer is panel anagrafica only — no Dokploy calls here.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Customer show copy uses panel translations.
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
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
    'w-full rounded-xl border border-neutral-200 bg-white py-2.5 px-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs';
</script>

<template>
    <AppLayout :title="customer.name" :description="customer.email || t('customers_form.show_fallback')" :full-width="true">
        <template #actions>
            <Link
                href="/customers"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 hover:text-neutral-900"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>{{ t('common.back') }}</span>
            </Link>
        </template>

        <div class="space-y-8">
            <!-- Customer Details Card -->
            <section class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs">
                <div class="border-b border-neutral-100 bg-neutral-50/50 px-6 py-4 sm:px-8">
                    <h2 class="text-sm font-bold text-neutral-900">{{ t('customers_form.profile') }}</h2>
                </div>

                <form class="p-6 sm:p-8 space-y-6" @submit.prevent="saveCustomer">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="customer-name">
                                {{ t('common.name') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="customer-name"
                                v-model="customerForm.name"
                                type="text"
                                required
                                :class="inputClass"
                            />
                            <p v-if="customerForm.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ customerForm.errors.name }}
                            </p>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="customer-email">
                                    {{ t('common.email') }}
                                </label>
                                <span class="text-[11px] text-neutral-400">Optional</span>
                            </div>
                            <input
                                id="customer-email"
                                v-model="customerForm.email"
                                type="email"
                                :class="inputClass"
                            />
                            <p v-if="customerForm.errors.email" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ customerForm.errors.email }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="customer-notes">
                                    {{ t('common.notes') }}
                                </label>
                                <span class="text-[11px] text-neutral-400">Optional</span>
                            </div>
                            <textarea
                                id="customer-notes"
                                v-model="customerForm.notes"
                                rows="3"
                                :class="inputClass"
                            />
                            <p v-if="customerForm.errors.notes" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ customerForm.errors.notes }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-neutral-100">
                        <div class="flex items-center gap-3">
                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50"
                                :disabled="customerForm.processing"
                            >
                                <span>{{ t('customers_form.save') }}</span>
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50/60 px-4 py-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-50 transition"
                                :disabled="deleteCustomerForm.processing || customer.subscriptions.length > 0"
                                @click="deleteCustomer"
                            >
                                <span>{{ t('customers_form.delete') }}</span>
                            </button>
                        </div>

                        <p v-if="customer.subscriptions.length > 0" class="text-xs text-neutral-400">
                            {{ t('customers_form.delete_blocked') }}
                        </p>
                    </div>

                    <p v-if="deleteCustomerForm.errors.customer" class="text-xs font-medium text-rose-600">
                        {{ deleteCustomerForm.errors.customer }}
                    </p>
                </form>
            </section>

            <!-- Create New Space Section -->
            <section class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs">
                <div class="border-b border-neutral-100 bg-neutral-50/50 px-6 py-4 sm:px-8">
                    <h2 class="text-sm font-bold text-neutral-900">{{ t('customers_form.new_space') }}</h2>
                    <p class="mt-0.5 text-xs text-neutral-500">
                        {{ t('customers_form.new_space_help') }}
                    </p>
                </div>

                <form class="p-6 sm:p-8 space-y-6" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="space-name">
                                {{ t('customers_form.space_name') }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                id="space-name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="e.g. production-space"
                                :class="inputClass"
                            />
                            <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="service_plan_id">
                                {{ t('common.plan') }} <span class="text-rose-500">*</span>
                            </label>
                            <select id="service_plan_id" v-model="form.service_plan_id" required :class="inputClass">
                                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.service_plan_id" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ form.errors.service_plan_id }}
                            </p>
                            <p v-if="plans.length === 0" class="mt-1.5 text-xs text-amber-700">
                                {{ t('customers_form.plan_required') }}
                            </p>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-neutral-100">
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50"
                            :disabled="form.processing || plans.length === 0"
                        >
                            <span>{{ t('customers_form.create_space') }}</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Existing Spaces List Section -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-neutral-700">
                        {{ t('customers.spaces') }} ({{ customer.subscriptions.length }})
                    </h2>
                </div>

                <div
                    v-if="customer.subscriptions.length === 0"
                    class="rounded-2xl border border-dashed border-neutral-300 bg-white px-6 py-12 text-center text-xs text-neutral-500 shadow-xs"
                >
                    {{ t('customers_form.empty_spaces') }}
                </div>

                <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="subscription in customer.subscriptions"
                        :key="subscription.id"
                        class="rounded-2xl border border-neutral-200/80 bg-white p-5 shadow-xs hover:border-neutral-300 transition"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="`/subscriptions/${subscription.id}`"
                                    class="font-semibold text-neutral-900 hover:underline block truncate text-sm"
                                >
                                    {{ subscription.name }}
                                </Link>
                                <p class="mt-1 text-xs text-neutral-500">
                                    {{ subscription.service_plan?.name || t('common.plan') }} ·
                                    {{ t('customers_form.domains_count', { count: subscription.domains_count || 0 }) }}
                                </p>
                            </div>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-100">
                                {{ subscription.status || 'Active' }}
                            </span>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
