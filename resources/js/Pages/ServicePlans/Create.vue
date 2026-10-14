<!-- Create.vue — Create service plan component.

  exports: none
  used_by: routes/web.php
  rules:   Catalog of plans to attach to spaces.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

const form = useForm({
    name: 'Unlimited',
    slug: 'unlimited',
    max_domains: '',
    disk_mb: '',
});

const submit = () => {
    form.post('/service-plans');
};

const inputClass =
    'w-full rounded-xl border border-neutral-200 bg-white py-2.5 px-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs';
</script>

<template>
    <AppLayout :title="t('plans.create_title')" :description="t('plans.create_description')" :full-width="true">
        <template #actions>
            <Link
                href="/service-plans"
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
                    <!-- Plan Name -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="plan-name">
                            {{ t('common.name') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="plan-name"
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Starter Plan"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Plan Slug -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="plan-slug">
                                {{ t('common.slug') }}
                            </label>
                            <span class="text-[11px] text-neutral-400">Optional</span>
                        </div>
                        <input
                            id="plan-slug"
                            v-model="form.slug"
                            type="text"
                            placeholder="starter-plan"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.slug" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <!-- Max Domains -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="max_domains">
                                {{ t('plans.max_domains') }}
                            </label>
                            <span class="text-[11px] text-neutral-400">Empty = Unlimited</span>
                        </div>
                        <input
                            id="max_domains"
                            v-model="form.max_domains"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                            :class="inputClass"
                        />
                    </div>

                    <!-- Disk MB -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="disk_mb">
                                {{ t('plans.disk_mb') }}
                            </label>
                            <span class="text-[11px] text-neutral-400">Empty = Unlimited</span>
                        </div>
                        <input
                            id="disk_mb"
                            v-model="form.disk_mb"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                            :class="inputClass"
                        />
                    </div>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="flex items-center justify-end gap-3 border-t border-neutral-100 bg-neutral-50/60 px-6 py-4 sm:px-8">
                <Link
                    href="/service-plans"
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
