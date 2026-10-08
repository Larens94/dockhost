<!-- ResetPassword.vue — Imposta nuova password da link email.

  exports: none
  used_by: NewPasswordController
  rules:   Token in hidden field only — never display in UI.
  agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Reset password page.
-->

<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password');
};
</script>

<template>
    <div class="flex min-h-screen bg-neutral-50">
        <Head title="Nuova password" />
        <div class="flex flex-1 items-center justify-center px-6 py-12">
            <form
                class="w-full max-w-sm space-y-5 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div>
                    <h1 class="text-xl font-semibold tracking-normal">Imposta password</h1>
                    <p class="mt-1 text-sm text-zinc-500">Scegli una password per accedere al pannello hosting.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="email">Email</label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        required
                        readonly
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-zinc-50 px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium" for="password">Nuova password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="password_confirmation">Conferma password</label>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        required
                        class="mt-1.5 w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-400 focus:ring-2 focus:ring-neutral-900/10"
                    />
                </div>
                <p v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</p>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-zinc-900 px-3.5 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Salva password
                </button>
            </form>
        </div>
    </div>
</template>
