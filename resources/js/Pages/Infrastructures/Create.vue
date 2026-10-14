<!-- Create.vue — Create infrastructure form (Inertia).

  exports: defineProps | form | pickerServices
  used_by: app/Http/Controllers/InfrastructureController.php → create/store
  rules:   Creating an infra provisions ONE Dokploy compose; services share dokploy-network (server-side).
           Do NOT toggle Dokploy Isolated Deployments ON. Do NOT mention {slug}-db/{slug}-storage as the create model.
           Never show API keys or generated DB/SFTP passwords beyond one-time flash from backend.
  agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Shared-network create guidance
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
-->

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import ServicePicker from '../../Components/ServicePicker.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();

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
    'w-full rounded-xl border border-neutral-200 bg-white py-2.5 px-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs';
</script>

<template>
    <AppLayout
        :title="t('infrastructures.create_title')"
        :description="t('infrastructures.create_description')"
        :full-width="true"
    >
        <template #actions>
            <Link
                href="/infrastructures"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 hover:text-neutral-900"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>{{ t('common.back') }}</span>
            </Link>
        </template>

        <form
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs transition space-y-6"
            @submit.prevent="submit"
        >
            <div class="p-6 sm:p-8 space-y-6">
                <!-- Helper Notice -->
                <div class="rounded-xl border border-neutral-200/80 bg-neutral-50/70 p-4 text-xs text-neutral-600">
                    {{ t('infrastructures.wildcard_help', { host: `${form.slug || 'slug'}-mariadb` }) }}
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Slug -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="slug">
                            {{ t('common.slug') }} <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="slug"
                            v-model="form.slug"
                            type="text"
                            required
                            placeholder="e.g. infra1"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.slug" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <!-- Name -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700" for="name">
                                {{ t('common.name') }}
                            </label>
                            <span class="text-[11px] text-neutral-400">Optional</span>
                        </div>
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            :placeholder="form.slug || 'infra1'"
                            :class="inputClass"
                        />
                        <p v-if="form.errors.name" class="mt-1.5 text-xs font-medium text-rose-600">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <!-- Template -->
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="template">
                            {{ t('infrastructures.template') }}
                        </label>
                        <select id="template" v-model="form.template" :class="inputClass">
                            <option value="base">{{ t('infrastructures.template_base') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Service Picker Section -->
                <div class="pt-6 border-t border-neutral-100">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-1">
                        {{ t('infrastructures.services') }}
                    </h3>
                    <p class="text-xs text-neutral-500 mb-4">
                        {{ t('infrastructures.services_help') }}
                    </p>
                    <ServicePicker
                        :services="pickerServices"
                        :selected="form.enabled_services"
                        :disabled="form.processing"
                        @toggle="toggleOptional"
                    />
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="flex items-center justify-end gap-3 border-t border-neutral-100 bg-neutral-50/60 px-6 py-4 sm:px-8">
                <Link
                    href="/infrastructures"
                    class="rounded-xl px-4 py-2 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                >
                    {{ t('common.cancel') }}
                </Link>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing || !form.slug.trim()"
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
                    <span>{{ form.processing ? t('common.saving') : t('infrastructures.submit') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
