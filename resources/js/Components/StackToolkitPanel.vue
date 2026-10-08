<!-- StackToolkitPanel.vue — StackToolkitPanel component.

  exports: defineProps | emit:attach
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
-->

<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { usePanelTranslations } from '../composables/usePanelTranslations';

const { t } = usePanelTranslations();

const props = defineProps({
    domain: {
        type: Object,
        required: true,
    },
    stackPresets: {
        type: Object,
        default: () => ({}),
    },
    attachProcessing: {
        type: Boolean,
        default: false,
    },
    attachError: {
        type: String,
        default: '',
    },
    canOpenDokploy: {
        type: Boolean,
        default: true,
    },
    canMutateHosting: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['attach']);

const page = usePage();
const presetForm = useForm({});

const stack = computed(() => props.domain.stack || 'none');
const stackLabel = computed(() => props.domain.stack_label || t('common.hosting_only'));
const attached = computed(() => Boolean(props.domain.dokploy_application));
const dokployUrl = computed(() => props.domain.dokploy_application_url);
const isNone = computed(() => stack.value === 'none');
const isLaravel = computed(() => stack.value === 'laravel');

const dbAccount = computed(() => props.domain.database_accounts?.[0] || null);

const presetLines = computed(() =>
    Object.entries(props.stackPresets || {})
        .map(([key, value]) => `${key}=${value}`)
        .join('\n'),
);

const frameworkNote = computed(() => {
    if (['node', 'python', 'go'].includes(stack.value)) {
        return t('site.note_nixpacks');
    }

    if (stack.value === 'php') {
        return t('site.note_php');
    }

    if (stack.value === 'static') {
        return t('site.note_static');
    }

    return '';
});

const applyPreset = () => {
    presetForm.post(`/domains/${props.domain.id}/stack-preset`, { preserveScroll: true });
};
</script>

<template>
    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold">{{ t('site.heading', { stack: stackLabel }) }}</h2>
        <p class="mt-1 text-sm text-zinc-500">
            <template v-if="isNone">
                {{ t('site.none', { infra: domain.infra_slug }) }}
            </template>
            <template v-else>
                {{ t('site.app', { infra: domain.infra_slug, volume: `${domain.infra_slug}_data`, network: `${domain.infra_slug}-db` }) }}
            </template>
        </p>

        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg bg-zinc-50 px-3 py-2">
                <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.infra') }}</dt>
                <dd class="mt-1 text-sm font-medium">{{ domain.infra_slug }}</dd>
            </div>
            <div class="rounded-lg bg-zinc-50 px-3 py-2">
                <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('common.stack') }}</dt>
                <dd class="mt-1 text-sm font-medium">{{ stackLabel }}</dd>
            </div>
            <div v-if="dbAccount" class="rounded-lg bg-zinc-50 px-3 py-2 sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-zinc-500">{{ t('site.db_on_infra') }}</dt>
                <dd class="mt-1 text-sm font-medium">
                    {{ dbAccount.host }}:{{ dbAccount.port }} · {{ dbAccount.database_name }}
                </dd>
            </div>
        </dl>

        <p v-if="frameworkNote" class="mt-4 text-sm text-zinc-500">{{ frameworkNote }}</p>

        <p v-if="page.props.flash?.success" class="mt-3 text-sm text-emerald-700">
            {{ page.props.flash.success }}
        </p>
        <p v-if="presetForm.errors.stack_preset" class="mt-3 text-sm text-red-600">
            {{ presetForm.errors.stack_preset }}
        </p>
        <p v-if="attachError" class="mt-3 text-sm text-red-600">{{ attachError }}</p>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <a
                v-if="canOpenDokploy && dokployUrl"
                :href="dokployUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800"
            >
                {{ t('site.open_dokploy') }}
            </a>
            <button
                v-if="attached && presetLines"
                type="button"
                class="rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                :disabled="presetForm.processing || !canMutateHosting"
                @click="applyPreset"
            >
                {{ t('site.apply_preset') }}
            </button>
            <button
                v-if="isNone && !attached"
                type="button"
                class="rounded-lg border border-zinc-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50 disabled:opacity-50"
                :disabled="attachProcessing || !canMutateHosting"
                @click="emit('attach')"
            >
                {{ t('site.apply_laravel') }}
            </button>
        </div>

        <div v-if="presetLines && !isLaravel" class="mt-5">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ t('site.preset_heading') }}</p>
            <pre
                class="mt-2 overflow-x-auto rounded-lg bg-zinc-950 p-3 text-xs whitespace-pre-wrap text-zinc-100"
                >{{ presetLines }}</pre
            >
            <p class="mt-2 text-xs text-zinc-500">
                {{ t('site.preset_help') }}
            </p>
        </div>
    </section>
</template>
