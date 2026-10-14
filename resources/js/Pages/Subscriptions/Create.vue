<!-- Create.vue — Create space component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Attach customer to service plan.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Space create copy uses panel translations.
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

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

const inputClass =
    'w-full rounded-xl border border-neutral-200 bg-white py-2.5 px-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs';
</script>

<template>
    <AppLayout :title="t('spaces_form.create_title')" :description="t('spaces_form.create_description')" :full-width="true">
        <template #actions>
            <Link
                href="/subscriptions"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 hover:text-neutral-900"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>{{ t('common.back') }}</span>
            </Link>
        </template>

        <form
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs transition"
            @submit.prevent="submit"
        >
            <div class="p-6 sm:p-8 space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Customer Select -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="customer_id">
                            {{ t('common.customer') }} <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="customer_id"
                            v-model="form.customer_id"
                            required
                            :class="inputClass"
                        >
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.customer_id" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.customer_id }}
                        </p>
                    </div>

                    <!-- Service Plan Select -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="service_plan_id">
                            {{ t('common.plan') }} <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="service_plan_id"
                            v-model="form.service_plan_id"
                            required
                            :class="inputClass"
                        >
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                {{ plan.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.service_plan_id" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.service_plan_id }}
                        </p>
                    </div>

                    <!-- Space Name -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="name">
                            {{ t('spaces_form.space_name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. acme-web"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.name }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="flex items-center justify-end gap-3 border-t border-neutral-100 bg-neutral-50/60 px-6 py-4 sm:px-8">
                <Link
                    href="/subscriptions"
                    class="rounded-xl px-4 py-2 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                >
                    {{ t('common.cancel') }}
                </Link>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing || !form.name.trim()"
                >
                    <svg
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin text-white"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>{{ form.processing ? t('common.saving') : t('common.create') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
