<!-- DeleteSpaceModal.vue — Confirm space deletion by typing the domain or space name.

  exports: defineProps | defineEmits
  used_by: resources/js/Pages/Subscriptions/Index.vue
           resources/js/Pages/Subscriptions/Show.vue
  rules:   Lists Dokploy services and domains that deletion removes. Submit stays disabled until the typed text matches deletion_confirmation.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | GitHub-style delete confirmation for a space.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Modal copy uses panel translations.
-->

<script setup>
import { usePanelTranslations } from '../composables/usePanelTranslations';
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const { t } = usePanelTranslations();

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const form = useForm({
    confirmation: '',
});

const targets = computed(() => props.space.deletion_targets ?? []);

const asksForDomain = computed(
    () => targets.value.length === 1 && props.space.deletion_confirmation === targets.value[0].fqdn,
);

const confirmationMatches = computed(() => form.confirmation === (props.space.deletion_confirmation ?? ''));

const close = () => {
    if (form.processing) {
        return;
    }

    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    if (!confirmationMatches.value) {
        return;
    }

    form.delete(`/subscriptions/${props.space.id}`, {
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
};
</script>

<template>
    <div
        class="fixed inset-0 z-40 flex items-center justify-center bg-zinc-900/40 p-4"
        role="presentation"
        @click.self="close"
    >
        <div
            class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl border border-neutral-200 bg-white p-6 shadow-lg"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-space-title"
        >
            <h3 id="delete-space-title" class="text-base font-semibold text-red-800">
                {{ t('spaces.modal.title', { name: space.name }) }}
            </h3>
            <p class="mt-2 text-sm break-words text-zinc-600 [overflow-wrap:anywhere]">
                {{ t('spaces.modal.body') }}
            </p>

            <ul v-if="targets.length > 0" class="mt-4 space-y-2">
                <li
                    v-for="target in targets"
                    :key="target.fqdn"
                    class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm"
                >
                    <p class="font-medium break-all text-zinc-900">{{ target.fqdn }}</p>
                    <p class="mt-0.5 break-words text-zinc-600 [overflow-wrap:anywhere]">
                        <span v-if="target.dokploy_service">{{ t('spaces.modal.dokploy_service', { service: target.dokploy_service }) }}</span>
                        <span v-else>{{ t('spaces.modal.no_dokploy_service') }}</span>
                        · {{ target.stack_label }}
                        <template v-if="target.infra_slug"> · {{ target.infra_slug }}</template>
                    </p>
                </li>
            </ul>
            <p v-else class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-600">
                {{ t('spaces.modal.no_targets') }}
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium" for="confirm-space">
                        {{ t('spaces.modal.confirm_lead') }}
                        {{ asksForDomain ? t('spaces.modal.target_domain') : t('spaces.modal.target_space') }}
                        <span class="font-semibold">{{ space.deletion_confirmation }}</span>
                    </label>
                    <input
                        id="confirm-space"
                        v-model="form.confirmation"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                    />
                    <p v-if="form.errors.confirmation" class="mt-1 text-sm text-red-600">
                        {{ form.errors.confirmation }}
                    </p>
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-neutral-200 bg-white px-3.5 py-2 text-sm font-medium hover:bg-zinc-50"
                        :disabled="form.processing"
                        @click="close"
                    >
                        {{ t('spaces.modal.cancel') }}
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-red-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-red-800 disabled:opacity-50"
                        :disabled="form.processing || !confirmationMatches"
                    >
                        {{ t('spaces.modal.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
