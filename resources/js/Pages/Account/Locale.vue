<!-- Account/Locale.vue — Session language setting for authenticated panel users.

  exports: none
  used_by: LocaleController
  rules:   Choice is it or en and is stored in the Laravel session, not on the user row.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Language settings screen.
-->

<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { usePanelTranslations } from '../../composables/usePanelTranslations';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const { t } = usePanelTranslations();

const supportedLocales = computed(() => (Array.isArray(page.props.supportedLocales) ? page.props.supportedLocales : []));
const flashSuccess = computed(() => page.props.flash?.success);

const form = useForm({
    locale: typeof page.props.locale === 'string' ? page.props.locale : 'it',
});

const save = () => {
    form.put('/account/locale', { preserveScroll: true });
};
</script>

<template>
    <AppLayout :title="t('locale_settings.title')" :description="t('locale_settings.description')">
        <div class="mx-auto max-w-xl space-y-6">
            <p
                v-if="flashSuccess"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </p>

            <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">{{ t('locale_settings.heading') }}</h2>
                <p class="mt-2 text-sm text-zinc-600">{{ t('locale_settings.help') }}</p>

                <form class="mt-6 space-y-4" @submit.prevent="save">
                    <fieldset class="space-y-2">
                        <legend class="sr-only">{{ t('locale_settings.heading') }}</legend>
                        <label
                            v-for="option in supportedLocales"
                            :key="option.code"
                            class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-sm"
                            :class="form.locale === option.code ? 'border-zinc-900 bg-zinc-50' : 'border-neutral-200'"
                        >
                            <input
                                v-model="form.locale"
                                type="radio"
                                name="locale"
                                class="h-4 w-4 border-neutral-300 text-zinc-900 focus:ring-zinc-900/20"
                                :value="option.code"
                            />
                            <span class="font-medium text-neutral-900">{{ option.label }}</span>
                        </label>
                    </fieldset>

                    <p v-if="form.errors.locale" class="text-sm text-red-600">{{ form.errors.locale }}</p>

                    <button
                        type="submit"
                        class="rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ t('locale_settings.save') }}
                    </button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
