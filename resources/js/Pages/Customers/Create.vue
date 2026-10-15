<!-- Create.vue — Create customer component.

  exports: none
  used_by: routes/web.php
  rules:   Customer is panel anagrafica only — no Dokploy calls here.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Customer create copy uses panel translations.
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

const form = useForm({
    name: '',
    email: '',
    notes: '',
});

const submit = () => {
    form.post('/customers');
};
</script>

<template>
    <AppLayout :title="t('customers_form.create_title')" :description="t('customers_form.create_description')" :full-width="true">
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

        <!-- Clean Modern Creation Card -->
        <form
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs transition"
            @submit.prevent="submit"
        >
            <div class="p-6 sm:p-8 space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Customer Name -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="customer-name">
                            {{ t('common.name') }} <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative flex items-center">
                            <span class="pointer-events-none absolute left-3.5 text-neutral-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.75c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12l3 3v15"
                                    />
                                </svg>
                            </span>
                            <input
                                id="customer-name"
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Acme Corporation"
                                class="w-full rounded-xl border border-neutral-200 bg-white py-2.5 pl-10 pr-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
                                :class="form.errors.name ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : ''"
                            />
                        </div>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Customer Email -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="customer-email">
                                {{ t('common.email') }}
                            </label>
                            <span class="text-[11px] text-neutral-400 font-normal">Optional</span>
                        </div>
                        <div class="relative flex items-center">
                            <span class="pointer-events-none absolute left-3.5 text-neutral-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                                    />
                                </svg>
                            </span>
                            <input
                                id="customer-email"
                                v-model="form.email"
                                type="email"
                                placeholder="contact@acme.com"
                                class="w-full rounded-xl border border-neutral-200 bg-white py-2.5 pl-10 pr-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10"
                                :class="form.errors.email ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : ''"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.email }}</p>
                    </div>

                    <!-- Customer Notes -->
                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="customer-notes">
                                {{ t('common.notes') }}
                            </label>
                            <span class="text-[11px] text-neutral-400 font-normal">Optional</span>
                        </div>
                        <textarea
                            id="customer-notes"
                            v-model="form.notes"
                            rows="4"
                            placeholder="Internal notes, contact information, or contract details..."
                            class="w-full rounded-xl border border-neutral-200 bg-white p-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 resize-y"
                            :class="form.errors.notes ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : ''"
                        />
                        <p v-if="form.errors.notes" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Footer Action Toolbar -->
            <div class="flex items-center justify-end gap-3 border-t border-neutral-100 bg-neutral-50/60 px-6 py-4 sm:px-8">
                <Link
                    href="/customers"
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
