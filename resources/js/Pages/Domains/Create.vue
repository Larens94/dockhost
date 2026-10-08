<!-- Create.vue — Create component.

  exports: defineProps
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import AppLayout from '../../Layouts/AppLayout.vue';

const { t } = usePanelTranslations();
import LaravelLogo from '../../Components/LaravelLogo.vue';

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
    'mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10';
</script>

<template>
    <AppLayout
        :title="t('domains.create.title')"
        :description="t('domains.create.description', { space: subscription.name, customer: subscription.customer?.name || t('common.fallback_customer'), plan: subscription.service_plan?.name || t('common.fallback_plan') })"
    >
        <template #actions>
            <Link :href="`/subscriptions/${subscription.id}`" class="text-sm text-zinc-600 hover:text-zinc-900">
                {{ t('common.space') }}
            </Link>
        </template>

        <p class="mb-4 text-sm text-zinc-500">
            <Link :href="`/subscriptions/${subscription.id}`" class="hover:underline">{{ subscription.name }}</Link>
            <span class="mx-1.5 text-zinc-300">→</span>
            {{ t('domains.create.breadcrumb') }}
        </p>

        <form
            class="max-w-3xl space-y-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <p class="text-sm text-zinc-500">
                {{ t('domains.create.intro') }}
            </p>

            <div>
                <label class="block text-sm font-medium" for="fqdn">{{ t('common.fqdn') }}</label>
                <input
                    id="fqdn"
                    v-model="form.fqdn"
                    type="text"
                    required
                    placeholder="shop.example.com"
                    :class="inputClass"
                />
                <p v-if="form.errors.fqdn" class="mt-1 text-sm text-red-600">{{ form.errors.fqdn }}</p>
            </div>

            <fieldset>
                <legend class="text-sm font-medium">{{ t('domains.create.infra_legend') }}</legend>
                <p class="mt-1 text-xs text-zinc-500">
                    {{ t('domains.create.infra_help') }}
                </p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="infra in infrastructures"
                        :key="infra.id"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left text-sm transition"
                        :class="
                            form.infra_slug === infra.slug
                                ? 'border-zinc-900 bg-zinc-50 ring-2 ring-zinc-900/10'
                                : 'border-zinc-200 hover:border-zinc-300'
                        "
                        @click="selectInfra(infra.slug)"
                    >
                        <span class="block font-semibold">{{ infra.slug }}</span>
                        <span class="mt-0.5 block text-xs text-zinc-500">{{ infra.status }}</span>
                        <span class="mt-2 block text-xs text-zinc-500">
                            {{ infra.mysql_host }} · {{ infra.postgres_host }}
                        </span>
                    </button>
                </div>
                <p v-if="infrastructures.length === 0" class="mt-2 text-sm text-amber-700">
                    {{ t('domains.create.infra_empty') }}
                </p>
                <p v-if="form.errors.infra_slug" class="mt-2 text-sm text-red-600">{{ form.errors.infra_slug }}</p>
            </fieldset>

            <label class="flex items-start gap-2 text-sm">
                <input
                    v-model="form.create_database"
                    type="checkbox"
                    class="mt-1"
                    :disabled="!canCreateSelectedDatabase"
                />
                <span>
                    <span class="font-medium">{{ t('domains.create.create_database') }}</span>
                    <span class="block text-xs text-zinc-500">
                        {{ t('domains.create.create_database_help', { host: `${form.infra_slug || 'infra1'}-mariadb` }) }}
                    </span>
                </span>
            </label>
            <p v-if="selectedInfra && !canCreateSelectedDatabase" class="text-sm text-amber-700">
                {{ t('domains.create.engine_missing', { engine: form.engine === 'postgres' ? 'Postgres' : 'MariaDB' }) }}
            </p>
            <div v-if="form.create_database">
                <label class="block text-sm font-medium" for="engine">{{ t('domains.create.engine') }}</label>
                <select id="engine" v-model="form.engine" :class="inputClass">
                    <option value="mysql" :disabled="!selectedInfra?.can_mysql">{{ t('common.mysql') }}</option>
                    <option value="postgres" :disabled="!selectedInfra?.can_postgres">{{ t('common.postgres') }}</option>
                </select>
                <p v-if="form.errors.engine" class="mt-1 text-sm text-red-600">{{ form.errors.engine }}</p>
                <p v-if="form.errors.create_database" class="mt-1 text-sm text-red-600">
                    {{ form.errors.create_database }}
                </p>
            </div>

            <fieldset>
                <legend class="text-sm font-medium">{{ t('domains.create.stack_legend') }}</legend>
                <p class="mt-1 text-xs text-zinc-500">
                    {{ t('domains.create.stack_help') }}
                </p>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="stack in stacks"
                        :key="stack.value"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left text-sm transition"
                        :class="
                            form.stack === stack.value
                                ? 'border-zinc-900 bg-zinc-50 ring-2 ring-zinc-900/10'
                                : 'border-zinc-200 hover:border-zinc-300'
                        "
                        @click="selectStack(stack.value)"
                    >
                        <span class="flex items-start gap-3">
                            <LaravelLogo v-if="stack.is_laravel" />
                            <span
                                v-else
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-zinc-100 text-[10px] font-semibold tracking-wide text-zinc-700"
                            >
                                {{ stack.mark }}
                            </span>
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ stack.label }}</span>
                                <span class="mt-1 block text-xs text-zinc-500">
                                    <template v-if="stack.value === 'none'">
                                        {{ t('domains.create.stack_none') }}
                                    </template>
                                    <template v-else-if="stack.is_laravel">
                                        {{ t('domains.create.stack_laravel', { volume: `${form.infra_slug || 'infra'}_data` }) }}
                                    </template>
                                    <template v-else>
                                        {{ t('domains.create.stack_other') }}
                                    </template>
                                </span>
                            </span>
                        </span>
                    </button>
                </div>
            </fieldset>
            <p v-if="form.errors.stack" class="text-sm text-red-600">{{ form.errors.stack }}</p>
            <p v-if="selectedStack?.creates_application" class="text-xs text-zinc-500">
                {{ t('domains.create.after_create') }}
            </p>

            <button
                type="submit"
                class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                :disabled="form.processing || infrastructures.length === 0"
            >
                {{ t('domains.create.submit') }}
            </button>
        </form>
    </AppLayout>
</template>
