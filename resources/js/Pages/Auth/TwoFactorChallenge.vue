<!-- Auth/TwoFactorChallenge.vue — TOTP step after password login.

  exports: none
  used_by: TwoFactorChallengeController
  rules:   Accept 6-digit TOTP or recovery code format XXXX-XXXX.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Italian 2FA login challenge.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | 2FA challenge copy uses panel translations.
-->

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { usePanelTranslations } from '../../composables/usePanelTranslations';

const { t } = usePanelTranslations();

const form = useForm({
    code: '',
});

const submit = () => {
    form.post('/login/two-factor');
};
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-6">
        <Head :title="t('auth.two_factor_title')" />
        <form
            class="w-full max-w-sm space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div>
                <h1 class="text-xl font-semibold">{{ t('auth.two_factor_heading') }}</h1>
                <p class="mt-1 text-sm text-zinc-500">{{ t('auth.two_factor_help') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium" for="code">{{ t('auth.code') }}</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    autocomplete="one-time-code"
                    required
                    class="mt-1.5 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>
            <button
                type="submit"
                class="w-full rounded-lg bg-zinc-900 px-3.5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ t('auth.verify') }}
            </button>
        </form>
    </div>
</template>
