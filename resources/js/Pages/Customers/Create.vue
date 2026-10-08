<!-- Create.vue — Create component.

  exports: none
  used_by: none
  rules:   none
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Customer create copy uses panel translations.
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
    <AppLayout :title="t('customers_form.create_title')" :description="t('customers_form.create_description')">
        <template #actions>
            <Link href="/customers" class="text-sm text-zinc-600 hover:text-zinc-900">{{ t('common.back') }}</Link>
        </template>

        <form
            class="max-w-xl space-y-5 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <label class="block text-sm font-medium" for="name">{{ t('common.name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="email">{{ t('common.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="notes">{{ t('common.notes') }}</label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    class="mt-1.5 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-zinc-400 focus:ring-2 focus:ring-zinc-900/10"
                />
            </div>
            <button
                type="submit"
                class="rounded-lg bg-zinc-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ t('common.create') }}
            </button>
        </form>
    </AppLayout>
</template>
