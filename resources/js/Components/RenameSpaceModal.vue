<!-- RenameSpaceModal.vue — Edit space (subscription) display name.

  exports: defineProps | defineEmits
  used_by: resources/js/Pages/Subscriptions/Show.vue
  rules:   PUT subscriptions/{id} with unique name validation from server.
  agent:   composer-2.5 | cursor | 2026-10-16 | s_rename_space | Modal rename instead of inline form.
-->

<script setup>
import { usePanelTranslations } from '../composables/usePanelTranslations';
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
    name: props.space.name,
});

const close = () => {
    if (form.processing) {
        return;
    }

    form.reset();
    form.clearErrors();
    emit('close');
};

const submit = () => {
    form.put(`/subscriptions/${props.space.id}`, {
        preserveScroll: true,
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
            class="w-full max-w-md rounded-xl border border-neutral-200 bg-white p-6 shadow-lg"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rename-space-title"
        >
            <h3 id="rename-space-title" class="text-base font-semibold text-neutral-900">
                {{ t('spaces.modal.rename_title') }}
            </h3>
            <p class="mt-1 text-sm text-zinc-600">
                {{ t('spaces.modal.rename_body') }}
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-neutral-700" for="rename-space-name">
                        {{ t('spaces_form.space_name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="rename-space-name"
                        v-model="form.name"
                        type="text"
                        required
                        autocomplete="off"
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
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
                        class="rounded-lg bg-neutral-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ t('spaces_form.save_name') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
