<!-- Login.vue — Login component.

  exports: none
  used_by: LoginController
  rules:   Link to /forgot-password for self-service reset.
  agent:   codedna-cli (no-llm) | unknown | 2026-09-21 | unknown | initial CodeDNA annotation pass
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Italian copy + forgot password link.
  agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Login copy uses panel translations.
-->

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePanelTranslations } from '../../composables/usePanelTranslations';

const { t } = usePanelTranslations();
const page = usePage();
const appName = computed(() => page.props.appName || 'DokHosts');

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login');
};
</script>

<template>
    <div class="flex min-h-screen bg-neutral-50">
        <Head :title="t('auth.title')" />
        <div class="hidden w-[28rem] flex-col justify-between border-r border-neutral-800 bg-zinc-900 px-10 py-12 text-white lg:flex">
            <div class="flex items-center gap-2.5">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white text-xs font-semibold text-zinc-900">
                    DH
                </span>
                <span class="text-sm font-semibold tracking-normal">{{ appName }}</span>
            </div>
            <div>
                <p class="text-2xl font-semibold tracking-normal">{{ t('auth.aside_title') }}</p>
                <p class="mt-3 text-sm text-zinc-400">
                    {{ t('auth.aside_body') }}
                </p>
            </div>
            <p class="text-xs text-zinc-500">Silicore</p>
        </div>

        <div class="flex flex-1 items-center justify-center px-6 py-12">
            <form
                class="w-full max-w-sm space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div>
                    <h1 class="text-xl font-semibold tracking-normal">{{ t('auth.title') }}</h1>
                    <p class="mt-1 text-sm text-zinc-500">{{ t('auth.subtitle') }}</p>
                </div>
                <p v-if="page.props.flash?.status" class="text-sm text-emerald-700">{{ page.props.flash.status }}</p>
                <div>
                    <label class="block text-sm font-medium" for="email">{{ t('common.email') }}</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium" for="password">{{ t('common.password') }}</label>
                        <Link href="/forgot-password" class="text-xs text-zinc-600 hover:text-zinc-900 hover:underline">
                            {{ t('auth.forgot') }}
                        </Link>
                    </div>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-zinc-900 px-3.5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ t('auth.enter') }}
                </button>
            </form>
        </div>
    </div>
</template>
