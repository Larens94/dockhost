<!-- Create.vue — Create domain component.

  exports: defineProps
  used_by: routes/web.php
  rules:   Attach domain to subscription space on chosen infrastructure and stack.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   antigravity | 2026-10-14 | clean modern user-friendly redesign
  agent:   grok-4.7 | cursor | 2026-10-21 | s_generic_service | Generic stack card on domain create
-->

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';
import LaravelLogo from '../../Components/LaravelLogo.vue';

const { t } = usePanelTranslations();

const props = defineProps({
    subscription: {
        type: Object,
        required: true,
    },
    infrastructures: {
        type: Array,
        default: () => [],
    },
    stacks: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    fqdn: '',
    infra_slug: props.infrastructures[0]?.slug || '',
    create_database: false,
    engine: 'mysql',
    stack: 'none',
});

const selectInfra = (slug) => {
    form.infra_slug = slug;
    form.create_database = canCreateSelectedDatabase.value ? form.create_database : false;
};

const selectStack = (value) => {
    form.stack = value;
};

const selectedInfra = computed(() => props.infrastructures.find((infra) => infra.slug === form.infra_slug));

const selectedStack = computed(() => props.stacks.find((stack) => stack.value === form.stack));

const canCreateSelectedDatabase = computed(() => {
    if (!selectedInfra.value) {
        return false;
    }

    return form.engine === 'postgres' ? selectedInfra.value.can_postgres : selectedInfra.value.can_mysql;
});

const submit = () => {
    form.post(`/subscriptions/${props.subscription.id}/domains`);
};

const inputClass =
    'w-full rounded-xl border border-neutral-200 bg-white py-2.5 px-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs';
</script>

<template>
    <AppLayout
        :title="t('domains.create.title')"
        :description="t('domains.create.description', { space: subscription.name, customer: subscription.customer?.name || t('common.fallback_customer'), plan: subscription.service_plan?.name || t('common.fallback_plan') })"
        :full-width="true"
    >
        <template #actions>
            <Link
                :href="`/subscriptions/${subscription.id}`"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-2xs transition hover:bg-neutral-50 hover:text-neutral-900"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span>{{ t('common.space') }}</span>
            </Link>
        </template>

        <form
            class="overflow-hidden rounded-2xl border border-neutral-200/80 bg-white shadow-xs transition space-y-6"
            @submit.prevent="submit"
        >
            <div class="p-6 sm:p-8 space-y-8">
                <!-- Introductory Notice -->
                <p class="text-xs text-neutral-500 leading-relaxed">
                    {{ t('domains.create.intro') }}
                </p>

                <!-- FQDN Domain Name -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="fqdn">
                        {{ t('common.fqdn') }} <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative flex items-center">
                        <span class="pointer-events-none absolute left-3.5 text-neutral-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582" />
                            </svg>
                        </span>
                        <input
                            id="fqdn"
                            v-model="form.fqdn"
                            type="text"
                            required
                            placeholder="e.g. shop.example.com"
                            class="w-full rounded-xl border border-neutral-200 bg-white py-2.5 pl-10 pr-3.5 text-sm text-neutral-900 placeholder:text-neutral-400 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 shadow-2xs"
                            :class="form.errors.fqdn ? 'border-rose-300 focus:border-rose-500 focus:ring-rose-500/20' : ''"
                        />
                    </div>
                    <p v-if="form.errors.fqdn" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.fqdn }}</p>
                </div>

                <!-- Infrastructure Target -->
                <div class="pt-6 border-t border-neutral-100">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-1">
                        {{ t('domains.create.infra_legend') }} <span class="text-rose-500">*</span>
                    </h3>
                    <p class="text-xs text-neutral-500 mb-4">
                        {{ t('domains.create.infra_help') }}
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="infra in infrastructures"
                            :key="infra.id"
                            type="button"
                            class="flex flex-col rounded-2xl border p-4 text-left transition"
                            :class="
                                form.infra_slug === infra.slug
                                    ? 'border-neutral-900 bg-neutral-50/60 ring-2 ring-neutral-900/10 shadow-xs'
                                    : 'border-neutral-200 bg-white hover:border-neutral-300 hover:bg-neutral-50/30'
                            "
                            @click="selectInfra(infra.slug)"
                        >
                            <div class="flex items-center justify-between w-full">
                                <span class="font-bold text-sm text-neutral-900">{{ infra.slug }}</span>
                                <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold text-neutral-600 uppercase">
                                    {{ infra.status }}
                                </span>
                            </div>
                            <span class="mt-2 text-xs text-neutral-500 font-mono">
                                {{ infra.mysql_host }} · {{ infra.postgres_host }}
                            </span>
                        </button>
                    </div>

                    <p v-if="infrastructures.length === 0" class="mt-2 text-xs text-amber-700">
                        {{ t('domains.create.infra_empty') }}
                    </p>
                    <p v-if="form.errors.infra_slug" class="mt-2 text-xs font-medium text-rose-600">
                        {{ form.errors.infra_slug }}
                    </p>
                </div>

                <!-- Database Options -->
                <div class="pt-6 border-t border-neutral-100 space-y-4">
                    <label class="flex items-start gap-3 rounded-2xl border border-neutral-200/80 bg-neutral-50/40 p-4 cursor-pointer hover:bg-neutral-50/80 transition">
                        <input
                            v-model="form.create_database"
                            type="checkbox"
                            class="mt-1 h-4 w-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900"
                            :disabled="!canCreateSelectedDatabase"
                        />
                        <div class="min-w-0">
                            <span class="block text-xs font-bold text-neutral-900 uppercase tracking-wide">
                                {{ t('domains.create.create_database') }}
                            </span>
                            <span class="block text-xs text-neutral-500 mt-0.5">
                                {{ t('domains.create.create_database_help', { host: `${form.infra_slug || 'infra1'}-mariadb` }) }}
                            </span>
                        </div>
                    </label>

                    <p v-if="selectedInfra && !canCreateSelectedDatabase" class="text-xs text-amber-700">
                        {{ t('domains.create.engine_missing', { engine: form.engine === 'postgres' ? 'Postgres' : 'MariaDB' }) }}
                    </p>

                    <div v-if="form.create_database" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-2" for="engine">
                                {{ t('domains.create.engine') }}
                            </label>
                            <select id="engine" v-model="form.engine" :class="inputClass">
                                <option value="mysql" :disabled="!selectedInfra?.can_mysql">{{ t('common.mysql') }}</option>
                                <option value="postgres" :disabled="!selectedInfra?.can_postgres">{{ t('common.postgres') }}</option>
                            </select>
                            <p v-if="form.errors.engine" class="mt-1.5 text-xs font-medium text-rose-600">{{ form.errors.engine }}</p>
                            <p v-if="form.errors.create_database" class="mt-1.5 text-xs font-medium text-rose-600">
                                {{ form.errors.create_database }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Application Stack Selection -->
                <div class="pt-6 border-t border-neutral-100">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-700 mb-1">
                        {{ t('domains.create.stack_legend') }} <span class="text-rose-500">*</span>
                    </h3>
                    <p class="text-xs text-neutral-500 mb-4">
                        {{ t('domains.create.stack_help') }}
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <button
                            v-for="stack in stacks"
                            :key="stack.value"
                            type="button"
                            class="flex items-start gap-3.5 rounded-2xl border p-4 text-left transition"
                            :class="
                                form.stack === stack.value
                                    ? 'border-neutral-900 bg-neutral-50/60 ring-2 ring-neutral-900/10 shadow-xs'
                                    : 'border-neutral-200 bg-white hover:border-neutral-300 hover:bg-neutral-50/30'
                            "
                            @click="selectStack(stack.value)"
                        >
                            <LaravelLogo v-if="stack.is_laravel" />
                            <span
                                v-else
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-[11px] font-bold tracking-wide text-neutral-800"
                            >
                                {{ stack.mark }}
                            </span>
                            <div class="min-w-0">
                                <span class="block font-bold text-sm text-neutral-900">{{ stack.label }}</span>
                                <span class="mt-1 block text-xs text-neutral-500">
                                    <template v-if="stack.value === 'none'">
                                        {{ t('domains.create.stack_none') }}
                                    </template>
                                    <template v-else-if="stack.value === 'generic'">
                                        {{ t('domains.create.stack_generic') }}
                                    </template>
                                    <template v-else-if="stack.is_laravel">
                                        {{ t('domains.create.stack_laravel', { volume: `${form.infra_slug || 'infra'}_data` }) }}
                                    </template>
                                    <template v-else>
                                        {{ t('domains.create.stack_other') }}
                                    </template>
                                </span>
                            </div>
                        </button>
                    </div>

                    <p v-if="form.errors.stack" class="mt-2 text-xs font-medium text-rose-600">{{ form.errors.stack }}</p>
                    <p v-if="selectedStack?.creates_application" class="mt-2 text-xs text-neutral-500">
                        {{ t('domains.create.after_create') }}
                    </p>
                </div>
            </div>

            <!-- Action Toolbar -->
            <div class="flex items-center justify-end gap-3 border-t border-neutral-100 bg-neutral-50/60 px-6 py-4 sm:px-8">
                <Link
                    :href="`/subscriptions/${subscription.id}`"
                    class="rounded-xl px-4 py-2 text-xs font-semibold text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 transition"
                >
                    {{ t('common.cancel') }}
                </Link>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-neutral-900 px-5 py-2.5 text-xs font-semibold text-white shadow-xs transition hover:bg-neutral-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="form.processing || infrastructures.length === 0 || !form.fqdn.trim()"
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
                    <span>{{ form.processing ? t('common.saving') : t('domains.create.submit') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
